import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createServer, request } from 'node:http';
import { once } from 'node:events';
import { createDemoServer } from './server.mjs';

async function listen(server, context) {
  server.listen(0, '127.0.0.1');
  await once(server, 'listening');
  context.after(() => new Promise((resolve) => server.close(resolve)));
  return `http://127.0.0.1:${server.address().port}`;
}

test('serves deep email links and public Google configuration without exposing environment files', async (context) => {
  const url = await listen(createDemoServer({ apiUrl: 'http://127.0.0.1:1', googleClientId: 'public-client' }), context);
  const configuration = await fetch(`${url}/config.json`);
  assert.deepEqual(await configuration.json(), { google_client_id: 'public-client' });
  assert.equal(configuration.headers.get('cache-control'), 'no-store');
  for (const path of ['/', '/auth/register?token=mail-token', '/auth/accept-invitation?token=mail-token']) {
    const page = await fetch(url + path);
    assert.equal(page.status, 200);
    assert.match(await page.text(), /Account demo/);
    assert.equal(page.headers.get('referrer-policy'), 'strict-origin-when-cross-origin');
  }
  for (const path of ['/.env', '/server.mjs', '/package.json', '/api/other', '/public/%2e%2e/.env']) {
    assert.equal((await fetch(url + path)).status, 404);
  }
});

test('proxies authenticated JSON requests and preserves API errors without forwarding browser cookies', async (context) => {
  let received;
  const upstream = await listen(createServer(async (request, response) => {
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    received = { path: request.url, method: request.method, headers: request.headers, body: JSON.parse(Buffer.concat(chunks).toString()) };
    response.writeHead(403, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify({ code: 'profile_incomplete' }));
  }), context);
  const url = await listen(createDemoServer({ apiUrl: upstream }), context);
  const result = await fetch(`${url}/api/v1/auth/organizations?response=resource`, {
    method: 'POST', headers: {
      Authorization: 'Bearer demo-session', 'Content-Type': 'application/json',
      Origin: 'http://localhost:28421', Cookie: 'irrelevant=secret',
    }, body: JSON.stringify({ name: 'Demo organization' }),
  });
  assert.equal(result.status, 403);
  assert.deepEqual(await result.json(), { code: 'profile_incomplete' });
  assert.equal(received.path, '/v1/auth/organizations?response=resource');
  assert.equal(received.method, 'POST');
  assert.equal(received.headers.authorization, 'Bearer demo-session');
  assert.equal(received.headers.origin, 'http://localhost:28421');
  assert.equal(received.headers.cookie, undefined);
  assert.deepEqual(received.body, { name: 'Demo organization' });
});

test('reports an unavailable backend as a recoverable JSON error', async (context) => {
  const url = await listen(createDemoServer({ apiUrl: 'http://127.0.0.1:1' }), context);
  const result = await fetch(`${url}/api/v1/auth/me`);
  assert.equal(result.status, 502);
  assert.equal(typeof (await result.json()).message, 'string');
});


test('preserves throttling headers so the browser can show the retry delay', async (context) => {
  const upstream = await listen(createServer((request, response) => {
    response.writeHead(429, {
      'Content-Type': 'application/json', 'Retry-After': '42',
      'X-RateLimit-Limit': '20', 'X-RateLimit-Remaining': '0', 'X-RateLimit-Reset': '1790000000',
    });
    response.end(JSON.stringify({ message: 'Too Many Attempts.' }));
  }), context);
  const url = await listen(createDemoServer({ apiUrl: upstream }), context);
  const result = await fetch(`${url}/api/v1/auth/google-challenges`, { method: 'POST' });
  assert.equal(result.status, 429);
  assert.equal(result.headers.get('retry-after'), '42');
  assert.equal(result.headers.get('x-ratelimit-limit'), '20');
  assert.equal(result.headers.get('x-ratelimit-remaining'), '0');
  assert.equal(result.headers.get('x-ratelimit-reset'), '1790000000');
});


test('redirects a loopback alias to the configured frontend while preserving email link parameters', async (context) => {
  const server = createDemoServer({ apiUrl: 'http://127.0.0.1:1', frontendUrl: 'http://localhost:28421' });
  const url = await listen(server, context);
  const requestWithHost = (path, host) => new Promise((resolve, reject) => {
    const outgoing = request(url + path, { headers: { Host: host } }, (response) => {
      response.resume();
      response.on('end', () => resolve(response));
    });
    outgoing.on('error', reject);
    outgoing.end();
  });
  const result = await requestWithHost('/auth/accept-invitation?token=mail-token&email=person%40example.test', '127.0.0.1:28421');
  assert.equal(result.statusCode, 302);
  assert.equal(result.headers.location, 'http://localhost:28421/auth/accept-invitation?token=mail-token&email=person%40example.test');
  const canonical = await requestWithHost('/', 'localhost:28421');
  assert.equal(canonical.statusCode, 200);
  const unrelated = await requestWithHost('/', 'attacker.test:28421');
  assert.equal(unrelated.headers.location, undefined);
});

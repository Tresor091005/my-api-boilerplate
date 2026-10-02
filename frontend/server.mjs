import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const publicDirectory = new URL('./public/', import.meta.url);
const assets = new Map([
  ['/', ['index.html', 'text/html']],
  ['/auth/register', ['index.html', 'text/html']],
  ['/auth/accept-invitation', ['index.html', 'text/html']],
  ['/app.js', ['app.js', 'text/javascript']],
  ['/styles.css', ['styles.css', 'text/css']],
  ['/google-test.html', ['google-test.html', 'text/html']],
]);

/** Independent development server; exposes only public configuration and proxies the JSON API. */
export function createDemoServer({ apiUrl, googleClientId = '', frontendUrl }) {
  const backend = new URL(apiUrl);
  const canonicalFrontend = frontendUrl ? new URL(frontendUrl) : null;
  return createServer(async (request, response) => {
    response.setHeader('Cache-Control', 'no-store');
    response.setHeader('X-Content-Type-Options', 'nosniff');
    response.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    response.setHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
    try {
      const url = new URL(request.url, 'http://localhost');
      const requestedHost = new URL(`http://${request.headers.host || 'localhost'}`);
      const loopbackHosts = ['localhost', '127.0.0.1'];
      if (canonicalFrontend && ['GET', 'HEAD'].includes(request.method)
        && assets.get(url.pathname)?.[1] === 'text/html'
        && loopbackHosts.includes(canonicalFrontend.hostname) && loopbackHosts.includes(requestedHost.hostname)
        && requestedHost.port === canonicalFrontend.port && requestedHost.hostname !== canonicalFrontend.hostname) {
        response.writeHead(302, { Location: new URL(url.pathname + url.search, canonicalFrontend).href });
        response.end();
        return;
      }
      if (url.pathname === '/config.json' && request.method === 'GET') {
        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ google_client_id: googleClientId }));
        return;
      }
      if (url.pathname.startsWith('/api/v1/')) {
        const headers = {};
        for (const key of ['accept', 'content-type', 'authorization', 'origin', 'user-agent']) {
          if (request.headers[key]) {
            headers[key] = request.headers[key];
          }
        }
        const chunks = [];
        let size = 0;
        for await (const chunk of request) {
          size += chunk.length;
          if (size > 256 * 1024) {
            response.writeHead(413);
            response.end();
            return;
          }
          chunks.push(chunk);
        }
        const target = new URL(url.pathname.slice(4) + url.search, backend);
        const upstream = await fetch(target, {
          method: request.method,
          headers,
          body: ['GET', 'HEAD'].includes(request.method) ? undefined : Buffer.concat(chunks),
          redirect: 'manual',
          signal: AbortSignal.timeout(15000),
        });
        for (const key of ['retry-after', 'x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset']) {
          if (upstream.headers.has(key)) response.setHeader(key, upstream.headers.get(key));
        }
        response.writeHead(upstream.status, { 'Content-Type': upstream.headers.get('content-type') || 'application/json' });
        response.end(Buffer.from(await upstream.arrayBuffer()));
        return;
      }
      const asset = assets.get(url.pathname);
      if (!asset || !['GET', 'HEAD'].includes(request.method)) {
        response.writeHead(404);
        response.end();
        return;
      }
      const contents = await readFile(new URL(asset[0], publicDirectory));
      response.writeHead(200, { 'Content-Type': `${asset[1]}; charset=utf-8` });
      response.end(request.method === 'HEAD' ? undefined : contents);
    } catch {
      if (!response.headersSent) {
        response.writeHead(502, { 'Content-Type': 'application/json' });
      }
      response.end(JSON.stringify({ message: 'The API is unavailable. Check the application service.' }));
    }
  });
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  try {
    process.loadEnvFile(fileURLToPath(new URL('../.env', import.meta.url)));
  } catch (error) {
    if (error.code !== 'ENOENT') {
      throw error;
    }
  }
  const port = Number(process.env.DEMO_PORT || 28421);
  createDemoServer({
    apiUrl: process.env.DEMO_API_URL || process.env.APP_URL || 'http://localhost:28417',
    googleClientId: process.env.GOOGLE_CLIENT_ID || '',
    frontendUrl: process.env.FRONTEND_URL || 'http://localhost:28421',
  }).listen(port, process.env.DEMO_HOST || '127.0.0.1', () => {
    console.log(`IAM demo available at http://localhost:${port}`);
  });
}

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Route::get('/testing/client-ip', fn (Request $request): array => [
        'ip'     => $request->ip(),
        'secure' => $request->isSecure(),
        'host'   => $request->getHost(),
    ]);
});

/**
 * Send a request that reaches the API from the given TCP peer.
 *
 * @param  array<string, string>  $headers
 */
function requestClientIp(string $peerAddress, array $headers = []): TestResponse
{
    return currentTestCase()
        ->withServerVariables(['REMOTE_ADDR' => $peerAddress])
        ->getJson('/testing/client-ip', $headers);
}

/**
 * Send an empty email challenge through a proxy on behalf of a forwarded client.
 */
function requestEmailChallengeFrom(string $forwardedClient): TestResponse
{
    return currentTestCase()
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->postJson('/v1/auth/email-challenges', [], ['X-Forwarded-For' => $forwardedClient]);
}

it('ignores forwarded headers when no proxy is trusted', function (): void {
    config(['trustedproxy.proxies' => null]);

    requestClientIp('10.0.0.5', ['X-Forwarded-For' => '203.0.113.7', 'X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertJsonPath('ip', '10.0.0.5')
        ->assertJsonPath('secure', false);
});

it('takes only the client IP and scheme from a trusted proxy', function (string $trustedProxies): void {
    config(['trustedproxy.proxies' => $trustedProxies]);

    $response = requestClientIp('10.0.0.5', [
        'X-Forwarded-For'   => '203.0.113.7',
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host'  => 'spa.example.test',
    ])->assertOk();

    expect($response->json('ip'))->toBe('203.0.113.7')
        ->and($response->json('secure'))->toBeTrue()
        ->and($response->json('host'))->not->toBe('spa.example.test');
})->with([
    'single IP' => '10.0.0.5',
    'CIDR list' => '192.168.0.0/16, 10.0.0.0/8',
    'any proxy' => '*',
]);

it('ignores forwarded headers sent by a peer that is not a trusted proxy', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.5']);

    requestClientIp('198.51.100.9', ['X-Forwarded-For' => '203.0.113.7'])
        ->assertOk()
        ->assertJsonPath('ip', '198.51.100.9');
});

it('skips a client-forged address when explicit proxies are trusted but not when any proxy is', function (): void {
    $forwardedFor = ['X-Forwarded-For' => '1.2.3.4, 203.0.113.7'];

    config(['trustedproxy.proxies' => '10.0.0.0/8']);
    requestClientIp('10.0.0.5', $forwardedFor)->assertJsonPath('ip', '203.0.113.7');

    config(['trustedproxy.proxies' => '*']);
    requestClientIp('10.0.0.5', $forwardedFor)->assertJsonPath('ip', '1.2.3.4');
});

it('gives each forwarded client its own auth rate-limit bucket behind a trusted proxy', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.5']);

    foreach (range(1, 5) as $attempt) {
        expect(requestEmailChallengeFrom('203.0.113.7')->status())->not->toBe(429);
    }

    requestEmailChallengeFrom('203.0.113.7')->assertTooManyRequests();
    expect(requestEmailChallengeFrom('198.51.100.9')->status())->not->toBe(429);
});

it('shares one auth rate-limit bucket between forwarded clients when the proxy is not trusted', function (): void {
    config(['trustedproxy.proxies' => null]);

    foreach (range(1, 5) as $attempt) {
        expect(requestEmailChallengeFrom('203.0.113.7')->status())->not->toBe(429);
    }

    requestEmailChallengeFrom('198.51.100.9')->assertTooManyRequests();
});

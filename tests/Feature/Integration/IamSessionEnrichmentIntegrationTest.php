<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Jobs\EnrichSession;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\AuthService;
use Lahatre\Iam\Services\SessionService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    authContext()->clear();
    app('auth')->forgetGuards();
    $this->withoutMiddleware(ThrottleRequests::class);
    Queue::fake();
    config(['services.geoip.database' => base_path('tests/Fixtures/GeoIP/GeoIP2-City-Test.mmdb')]);
});

/**
 * GeoIP fixture from https://github.com/maxmind/MaxMind-DB/tree/main/test-data,
 * distributed under the accompanying MaxMind MIT/Apache licenses.
 *
 * @return array{record: PersonalAccessToken, data: SessionData, job: EnrichSession, token: string}
 */
function enrichedSession(string $userAgent): array
{
    $data = SessionData::fromArray(['ip_address' => '81.2.69.160', 'user_agent' => $userAgent]);
    $result = app(AuthService::class)->issueToken(User::factory()->create(), $data);
    $record = PersonalAccessToken::query()->firstOrFail();
    $record->update(['metadata' => [...$record->metadata, 'custom' => ['preserved' => true]]]);
    $job = queuedSessionEnrichments()->first();
    currentTestCase()->withToken($result['token'])->withHeader('User-Agent', $userAgent)
        ->withServerVariables(['REMOTE_ADDR' => $data->ipAddress]);

    return ['record' => $record, 'data' => $data, 'job' => $job, 'token' => $result['token']];
}

/** @return Collection<int, EnrichSession> */
function queuedSessionEnrichments(): Collection
{
    $queue = Queue::getFacadeRoot();
    if (!$queue instanceof QueueFake) {
        throw new LogicException('Session enrichment must be faked in this test.');
    }

    return $queue->pushed(EnrichSession::class);
}

dataset('session devices', [
    'Mac'     => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'Mac', 'macOS'],
    'Android' => ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', 'Phone', 'Android'],
    'Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'Computer', 'Windows'],
    'Linux'   => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'Computer', 'GNU/Linux'],
    'Infinix' => ['Mozilla/5.0 (Linux; Android 12; Infinix X669) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36', 'Infinix Hot 30i', 'Android'],
]);

it('enriches queued sessions and exposes their device and approximate location through the account API', function (string $userAgent, string $name, string $os): void {
    $session = enrichedSession($userAgent);
    expect($session['job']->queue)->toBe('default')
        ->and($session['job']->afterCommit)->toBeTrue()
        ->and($session['record']->getMeta('session.last_request.device'))->toBeNull();
    $session['job']->handle(app(SessionService::class));
    $this->getJson('/v1/auth/sessions')->assertOk()
        ->assertJsonPath('data.0.last_request.device.name', $name)
        ->assertJsonPath('data.0.last_request.device.os', $os)
        ->assertJsonPath('data.0.last_request.location.city', 'London')
        ->assertJsonPath('data.0.last_request.location.region_code', 'ENG')
        ->assertJsonPath('data.0.last_request.location.country_code', 'GB')
        ->assertJsonMissingPath('data.0.last_request.ip_address')
        ->assertJsonMissingPath('data.0.last_request.user_agent');
    $record = $session['record']->fresh();
    expect($record->getMeta('custom.preserved'))->toBeTrue()
        ->and($record->getMeta('session.last_request.ip_address'))->toBe('81.2.69.160')
        ->and($record->getMeta('session.last_request.user_agent'))->toBe($userAgent);
    Queue::assertPushed(EnrichSession::class, 1);
})->with('session devices');

it('does not enqueue enrichment for unchanged activity and preserves its latest timestamp', function (): void {
    $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
    $session = enrichedSession($userAgent);
    currentTestCase()->travel(2)->minutes();
    $this->getJson('/v1/auth/me')->assertOk();
    $at = $session['record']->fresh()->getMeta('session.last_request.at');
    $session['job']->handle(app(SessionService::class));
    expect($session['record']->fresh()->getMeta('session.last_request.at'))->toBe($at);
    $this->getJson('/v1/auth/sessions')->assertOk()->assertJsonPath('data.0.last_request.device.name', 'Mac');
    Queue::assertPushed(EnrichSession::class, 1);
});

it('invalidates outdated enrichment and ignores jobs for superseded request inputs', function (): void {
    $session = enrichedSession('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');
    $session['job']->handle(app(SessionService::class));
    currentTestCase()->withHeader('User-Agent', 'bruno-runtime/4.2.1')->getJson('/v1/auth/sessions')->assertOk()
        ->assertJsonPath('data.0.last_request.device', null);
    $session['job']->handle(app(SessionService::class));
    expect($session['record']->fresh()->getMeta('session.last_request.device'))->toBeNull();
    Queue::assertPushed(EnrichSession::class, 2);
    $latest = queuedSessionEnrichments()->last();
    $latest->handle(app(SessionService::class));
    $this->getJson('/v1/auth/sessions')->assertOk()->assertJsonPath('data.0.last_request.device', null)
        ->assertJsonPath('data.0.last_request.location.city', 'London');
});

it('enqueues a new location lookup when the client IP changes without replacing custom metadata', function (): void {
    $session = enrichedSession('bruno-runtime/4.2.1');
    $session['job']->handle(app(SessionService::class));
    currentTestCase()->withServerVariables(['REMOTE_ADDR' => '192.168.65.1'])->getJson('/v1/auth/me')->assertOk();
    Queue::assertPushed(EnrichSession::class, 2);
    $latest = queuedSessionEnrichments()->last();
    $latest->handle(app(SessionService::class));
    $this->getJson('/v1/auth/sessions')->assertOk()->assertJsonPath('data.0.last_request.location', null);
    expect($session['record']->fresh()->getMeta('custom.preserved'))->toBeTrue();
});

it('does not recreate revoked sessions or enrich a session through another owners identifier', function (): void {
    $session = enrichedSession('bruno-runtime/4.2.1');
    app(SessionService::class)->enrich($session['record']->id, $session['record']->tokenable_type, User::factory()->create()->id, $session['data']);
    expect($session['record']->fresh()->getMeta('session.last_request.location'))->toBeNull();
    $session['record']->delete();
    $session['job']->handle(app(SessionService::class));
    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('keeps device information when the local geolocation database is missing', function (): void {
    config(['services.geoip.database' => storage_path('missing-city-database.mmdb')]);
    $session = enrichedSession('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');
    $session['job']->handle(app(SessionService::class));
    $this->getJson('/v1/auth/sessions')->assertOk()->assertJsonPath('data.0.last_request.device.os', 'Windows')
        ->assertJsonPath('data.0.last_request.location', null);
});

it('skips expired sessions even before their records are pruned', function (string $expiration): void {
    $session = enrichedSession('bruno-runtime/4.2.1');
    if ($expiration === 'token') {
        $session['record']->update(['expires_at' => now()]);
    } else {
        config(['sanctum.expiration' => 1]);
        $session['record']->forceFill(['created_at' => now()->subMinutes(2)])->save();
    }
    $session['job']->handle(app(SessionService::class));
    expect($session['record']->fresh()->getMeta('session.last_request.location'))->toBeNull()
        ->and(PersonalAccessToken::query()->whereKey($session['record']->id)->exists())->toBeTrue();
})->with(['token', 'global']);

it('leaves unavailable or nonpublic IP locations empty', function (?string $ipAddress): void {
    $session = enrichedSession('bruno-runtime/4.2.1');
    $data = SessionData::fromArray(['ip_address' => $ipAddress, 'user_agent' => $session['data']->userAgent]);
    app(SessionService::class)->recordActivity($session['record'], $data);
    $latest = queuedSessionEnrichments()->last();
    $latest->handle(app(SessionService::class));
    expect($session['record']->fresh()->getMeta('session.last_request.location'))->toBeNull();
})->with([null, '127.0.0.1', '192.168.65.1', '::1', '8.8.8.8']);

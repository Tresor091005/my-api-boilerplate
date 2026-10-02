<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Jobs\SendLoginCode;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Notifications\EmailLoginCodeNotification;
use Lahatre\Iam\Services\EmailLoginService;
use Lahatre\Organization\Models\Organization;
use Lahatre\Shared\Enums\QueueName;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setPermissionsTeamId(null);
    app('auth')->forgetGuards();
    $this->withoutMiddleware(ThrottleRequests::class);
    Queue::fake();
    Notification::fake();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
});

/** @return array{id: string, job: SendLoginCode} */
function requestPasswordlessCode(string $email): array
{
    $response = currentTestCase()->postJson('/v1/auth/email-challenges', ['email' => $email])->assertOk();
    $job = queuedLoginCodes()->last();
    expect($job)->toBeInstanceOf(SendLoginCode::class);

    return ['id' => $response->json('challenge_id'), 'job' => $job];
}

it('queues an encrypted email code without creating an account or exposing account existence', function (): void {
    $user = User::factory()->create();
    $deleted = User::factory()->create();
    $deleted->delete();
    foreach ([$user->email, 'new@example.test', $deleted->email] as $email) {
        $response = $this->postJson('/v1/auth/email-challenges', ['email' => Str::toUpper($email)])->assertOk();
        expect($response->json())->toHaveKeys(['message', 'challenge_id']);
        expect($response->json())->not->toHaveKeys(['code', 'has_account']);
        expect($response->json('message'))->toBe(__('iam::messages.auth.login_code_sent'));
    }
    expect(User::withTrashed()->count())->toBe(2)->and(Organization::query()->count())->toBe(0);
    Queue::assertPushed(SendLoginCode::class, 2);
    Queue::assertPushedOn(QueueName::Email->value, SendLoginCode::class);
    $job = queuedLoginCodes()->first();
    expect($job)->toBeInstanceOf(ShouldBeEncrypted::class)->and($job->code)->toMatch('/\A[0-9]{6}\z/');
    $row = DB::table('iam_email_login_challenges')->where('id', $job->challengeId)->first();
    expect($row->code_hash)->not->toBe($job->code)->and(strlen($row->code_hash))->toBe(64);
    $job->handle(app(EmailLoginService::class));
    Notification::assertSentOnDemand(EmailLoginCodeNotification::class);
    expect(Notification::sent(new AnonymousNotifiable, EmailLoginCodeNotification::class)->first()->code)->toBe($job->code);
});

it('creates a verified user without any organization and issues a one-day Sanctum session', function (): void {
    $challenge = requestPasswordlessCode(' NEW@EXAMPLE.TEST ');
    $response = currentTestCase()->withHeader('User-Agent', 'Example Mobile App')->postJson('/v1/auth/email-challenge-verifications', [
        'challenge_id' => $challenge['id'], 'code' => $challenge['job']->code,
        'first_name'   => ' Ada ', 'last_name' => ' Lovelace ',
    ])->assertOk()->assertJsonPath('data.user.email', 'new@example.test')->assertJsonPath('data.token_type', 'Bearer');
    $user = User::query()->firstOrFail();
    $token = PersonalAccessToken::query()->firstOrFail();
    expect($user->first_name)->toBe('Ada')->and($user->last_name)->toBe('Lovelace')
        ->and($user->email_verified_at)->not->toBeNull()->and(OrganizationMember::query()->count())->toBe(0)
        ->and(Organization::query()->count())->toBe(0)
        ->and($token->expires_at->diffInSeconds(now(), absolute: true))->toBeGreaterThan(86390)
        ->and($token->getMeta('organization_id'))->toBeNull()
        ->and($token->getMeta('session.authentication_method'))->toBe('email_otp')
        ->and($token->getMeta('session.last_request.user_agent'))->toBe('Example Mobile App');
    $this->withToken($response->json('data.access_token'))->getJson('/v1/auth/me')->assertOk();
    $this->getJson('/v1/auth/current-permissions')->assertForbidden();
});

it('reuses an existing account without overwriting its profile and verifies its email', function (): void {
    $user = User::factory()->unverified()->create();
    $challenge = requestPasswordlessCode($user->email);
    $this->postJson('/v1/auth/email-challenge-verifications', [
        'challenge_id' => $challenge['id'], 'code' => $challenge['job']->code,
        'first_name'   => 'Ignored', 'last_name' => 'Ignored',
    ])->assertOk()->assertJsonPath('data.user.id', $user->id);
    expect(User::query()->count())->toBe(1)->and($user->fresh()->first_name)->toBe($user->first_name)
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

it('issues a verified session before asking a new account to complete its profile', function (): void {
    $challenge = requestPasswordlessCode('new@example.test');
    $payload = ['challenge_id' => $challenge['id'], 'code' => $challenge['job']->code];
    $response = $this->postJson('/v1/auth/email-challenge-verifications', $payload)->assertOk()
        ->assertJsonPath('data.user.first_name', null)->assertJsonPath('data.user.last_name', null)
        ->assertJsonPath('data.user.profile_complete', false);
    expect(User::query()->count())->toBe(1)
        ->and(DB::table('iam_email_login_challenges')->where('id', $challenge['id'])->value('consumed_at'))->not->toBeNull();
    $this->withToken($response->json('data.access_token'))->getJson('/v1/auth/me')->assertOk();
    $this->postJson('/v1/auth/organizations', [])->assertForbidden()->assertJsonPath('code', 'profile_incomplete');
    $this->patchJson('/v1/auth/me?response=resource', ['first_name' => 'New', 'last_name' => 'User'])
        ->assertOk()->assertJsonPath('data.profile_complete', true);
});

it('consumes a code once and makes the oldest queued email obsolete after resend', function (): void {
    $user = User::factory()->create();
    $first = requestPasswordlessCode($user->email);
    currentTestCase()->travel(61)->seconds();
    $second = requestPasswordlessCode($user->email);
    $second['job']->handle(app(EmailLoginService::class));
    $first['job']->handle(app(EmailLoginService::class));
    Notification::assertSentOnDemandTimes(EmailLoginCodeNotification::class, 1);
    $this->postJson('/v1/auth/email-challenge-verifications', ['challenge_id' => $first['id'], 'code' => $first['job']->code])->assertUnprocessable();
    $payload = ['challenge_id' => $second['id'], 'code' => $second['job']->code];
    $this->postJson('/v1/auth/email-challenge-verifications', $payload)->assertOk();
    $this->postJson('/v1/auth/email-challenge-verifications', $payload)->assertUnprocessable();
    expect($user->tokens()->count())->toBe(1);
});

it('persists failed attempts and prevents resend from resetting the five-attempt budget', function (): void {
    $user = User::factory()->create();
    $challenge = requestPasswordlessCode($user->email);
    $wrong = $challenge['job']->code === '000000' ? '999999' : '000000';
    for ($attempt = 0; $attempt < EmailLoginService::MAX_ATTEMPTS; $attempt++) {
        $this->postJson('/v1/auth/email-challenge-verifications', ['challenge_id' => $challenge['id'], 'code' => $wrong])->assertUnprocessable();
    }
    expect(DB::table('iam_email_login_challenges')->where('id', $challenge['id'])->value('attempts'))->toBe(5);
    currentTestCase()->travel(61)->seconds();
    $resent = requestPasswordlessCode($user->email);
    $this->postJson('/v1/auth/email-challenge-verifications', ['challenge_id' => $resent['id'], 'code' => $resent['job']->code])->assertUnprocessable();
    $resent['job']->handle(app(EmailLoginService::class));
    Notification::assertNothingSent();
    expect($user->tokens()->count())->toBe(0);
});

it('rejects unavailable challenges without issuing a token', function (string $reason): void {
    $user = User::factory()->create();
    $challenge = requestPasswordlessCode($user->email);
    if ($reason === 'expired') {
        DB::table('iam_email_login_challenges')->where('id', $challenge['id'])->update(['expires_at' => now()]);
    } elseif ($reason === 'deleted_user') {
        $user->delete();
    } else {
        $challenge['id'] = (string) Str::uuid7();
    }
    $this->postJson('/v1/auth/email-challenge-verifications', ['challenge_id' => $challenge['id'], 'code' => $challenge['job']->code])
        ->assertUnprocessable()->assertJsonPath('errors.type', 'EmailLoginException');
    expect(PersonalAccessToken::query()->count())->toBe(0);
})->with(['expired', 'deleted_user', 'unknown_challenge']);

it('applies resend cooldown and hourly limits with the same generic public response', function (): void {
    $first = $this->postJson('/v1/auth/email-challenges', ['email' => 'new@example.test'])->assertOk();
    $limited = $this->postJson('/v1/auth/email-challenges', ['email' => 'new@example.test'])->assertOk();
    expect($limited->json('message'))->toBe($first->json('message'))
        ->and($limited->json('challenge_id'))->toBe($first->json('challenge_id'));
    Queue::assertPushed(SendLoginCode::class, 1);
    for ($request = 0; $request < 5; $request++) {
        currentTestCase()->travel(61)->seconds();
        $this->postJson('/v1/auth/email-challenges', ['email' => 'new@example.test'])->assertOk();
    }
    Queue::assertPushed(SendLoginCode::class, 5);
});

it('validates the public passwordless payload', function (array $payload, string $field): void {
    $this->postJson('/v1/auth/email-challenge-verifications', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'missing code'      => [['challenge_id' => '019fda30-1b24-7ecf-a970-5493c9b94a42'], 'code'],
    'numeric code'      => [['challenge_id' => '019fda30-1b24-7ecf-a970-5493c9b94a42', 'code' => 123456], 'code'],
    'short code'        => [['challenge_id' => '019fda30-1b24-7ecf-a970-5493c9b94a42', 'code' => '12345'], 'code'],
    'invalid challenge' => [['challenge_id' => 'invalid', 'code' => '123456'], 'challenge_id'],
    'invalid name'      => [['challenge_id' => '019fda30-1b24-7ecf-a970-5493c9b94a42', 'code' => '123456', 'first_name' => []], 'first_name'],
]);

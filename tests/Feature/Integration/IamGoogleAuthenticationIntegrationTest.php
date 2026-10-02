<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Google\Auth\AccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Models\ExternalIdentity;
use Lahatre\Iam\Models\Invitation;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    authContext()->clear();
    app('auth')->forgetGuards();
    setPermissionsTeamId(null);
    $this->withoutMiddleware(ThrottleRequests::class);
    Queue::fake();
    config([
        'services.google.client_id' => 'test-client.apps.googleusercontent.com',
        'frontend.url'              => 'https://app.example.test/', 'cors.allowed_origins' => ['https://app.example.test'],
    ]);
    Cache::forget('iam:google:certificates');
    Http::preventStrayRequests();
    Http::fake([AccessToken::FEDERATED_SIGNON_CERT_URL => Http::response(['keys' => [googleSigningMaterial()['jwk']]], 200, ['Cache-Control' => 'public, max-age=3600'])]);
});

afterEach(function (): void {
    setPermissionsTeamId(null);
});

/** @return array{private: string, jwk: array<string, string>} */
function googleSigningMaterial(): array
{
    static $material;
    if ($material !== null) {
        return $material;
    }
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if ($key === false || !openssl_pkey_export($key, $private)) {
        throw new RuntimeException('Unable to generate a signing key for Google verification tests.');
    }
    $details = openssl_pkey_get_details($key);

    return $material = ['private' => $private, 'jwk' => [
        'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => 'test-google-key',
        'n'   => JWT::urlsafeB64Encode($details['rsa']['n']), 'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
    ]];
}

/** @param array<string, mixed> $claims */
function googleCredential(array $challenge, array $claims = []): string
{
    return JWT::encode(array_replace([
        'iss'        => 'https://accounts.google.com', 'aud' => config('services.google.client_id'),
        'sub'        => '100000000000000000001', 'email' => 'new.google@gmail.com', 'email_verified' => true,
        'given_name' => ' Google ', 'family_name' => ' Person ', 'nonce' => $challenge['nonce'],
        'iat'        => time(), 'exp' => time() + 3600,
    ], $claims), googleSigningMaterial()['private'], 'RS256', 'test-google-key');
}

function googleChallenge(): array
{
    return currentTestCase()->postJson('/v1/auth/google-challenges')->assertOk()->json();
}

function googleSignIn(array $claims = []): TestResponse
{
    $challenge = googleChallenge();

    return currentTestCase()->postJson('/v1/auth/google-challenge-verifications', [
        'challenge_id' => $challenge['challenge_id'], 'credential' => googleCredential($challenge, $claims),
    ]);
}

it('creates a verified Google account and a common UUID Sanctum session without an organization', function (): void {
    $response = googleSignIn()->assertOk()->assertJsonPath('data.user.first_name', 'Google')
        ->assertJsonPath('data.user.last_name', 'Person')->assertJsonPath('data.user.member_roles', [])
        ->assertJsonPath('data.user.current_member_role_id', null)->assertJsonPath('data.token_type', 'Bearer');
    $user = User::query()->firstOrFail();
    $identity = ExternalIdentity::query()->firstOrFail();
    $token = PersonalAccessToken::findToken($response->json('data.access_token'));
    expect($user->email_verified_at)->not->toBeNull()
        ->and($identity->user_id)->toBe($user->id)->and($identity->subject)->toBe('100000000000000000001')
        ->and(Str::isUuid($token->id, 7))->toBeTrue()
        ->and($token->getMeta('session.authentication_method'))->toBe('google')
        ->and($token->getMeta('organization_id'))->toBeNull()->and(Organization::query()->count())->toBe(0);
    $this->withToken($response->json('data.access_token'))->getJson('/v1/auth/me')->assertOk();
    $this->getJson('/v1/auth/sessions')->assertOk()->assertJsonPath('data.0.authentication_method', 'google');
});

it('finds a returning Google identity by subject and preserves the local email and profile', function (): void {
    $user = User::factory()->create(['email' => 'original@gmail.com', 'first_name' => 'Local']);
    ExternalIdentity::factory()->create(['user_id' => $user->id, 'subject' => '100000000000000000001']);
    googleSignIn(['email' => 'changed@gmail.com', 'iss' => 'accounts.google.com'])->assertOk()
        ->assertJsonPath('data.user.id', $user->id)->assertJsonPath('data.user.email', 'original@gmail.com')
        ->assertJsonPath('data.user.first_name', 'Local');
    expect(User::query()->count())->toBe(1)->and(ExternalIdentity::query()->count())->toBe(1);
});

it('accepts authoritative Workspace addresses for initial creation', function (): void {
    googleSignIn(['email' => 'person@company.test', 'hd' => 'company.test'])->assertOk();
    expect(User::query()->where('email', 'person@company.test')->firstOrFail()->email_verified_at)->not->toBeNull();
});

it('creates a Google session with missing names and completes the profile without another challenge', function (): void {
    $challenge = googleChallenge();
    $credential = googleCredential($challenge, ['family_name' => null]);
    $response = $this->postJson('/v1/auth/google-challenge-verifications', ['challenge_id' => $challenge['challenge_id'], 'credential' => $credential])
        ->assertOk()->assertJsonPath('data.user.last_name', null)->assertJsonPath('data.user.profile_complete', false);
    expect(User::query()->count())->toBe(1)->and(PersonalAccessToken::query()->count())->toBe(1);
    $this->withToken($response->json('data.access_token'))->postJson('/v1/auth/organizations', [])
        ->assertForbidden()->assertJsonPath('code', 'profile_incomplete');
    $this->patchJson('/v1/auth/me?response=resource', ['last_name' => ' Completed '])
        ->assertOk()->assertJsonPath('data.last_name', 'Completed')->assertJsonPath('data.profile_complete', true);
    $this->postJson('/v1/auth/google-challenge-verifications', ['challenge_id' => $challenge['challenge_id'], 'credential' => $credential])
        ->assertUnprocessable()->assertJsonPath('errors.type', 'GoogleAuthException');
});

it('uses OTP before linking an existing account or a non-authoritative address', function (bool $existing): void {
    $email = $existing ? 'existing@gmail.com' : 'external@example.test';
    $user = $existing ? User::factory()->create(['email' => $email, 'first_name' => 'Existing']) : null;
    $challenge = googleChallenge();
    $credential = googleCredential($challenge, ['email' => $email]);
    $this->postJson('/v1/auth/google-challenge-verifications', ['challenge_id' => $challenge['challenge_id'], 'credential' => $credential])
        ->assertOk()->assertJsonPath('status', 'email_verification_required')->assertJsonPath('email', $email)->assertJsonMissingPath('data.access_token');
    expect(User::query()->count())->toBe($existing ? 1 : 0)->and(ExternalIdentity::query()->count())->toBe(0)
        ->and(PersonalAccessToken::query()->count())->toBe(0);
    $codeResponse = $this->postJson('/v1/auth/email-challenges', ['email' => $email])->assertOk();
    $login = $this->postJson('/v1/auth/email-challenge-verifications', [
        'challenge_id' => $codeResponse->json('challenge_id'), 'code' => queuedLoginCodes()->last()->code,
        'first_name'   => 'Completed', 'last_name' => 'Profile',
    ])->assertOk();
    $this->withToken($login->json('data.access_token'))->postJson('/v1/auth/google-identities', [
        'challenge_id' => $challenge['challenge_id'], 'credential' => $credential,
    ])->assertCreated()->assertJsonMissingPath('data.access_token');
    expect(PersonalAccessToken::query()->count())->toBe(1)->and(ExternalIdentity::query()->count())->toBe(1);
    $google = googleSignIn(['email' => $email])->assertOk();
    if ($existing) {
        $google->assertJsonPath('data.user.id', $user->id)->assertJsonPath('data.user.first_name', 'Existing');
    }
})->with([true, false]);

it('rejects invalid Google credentials without creating users identities or sessions', function (string $state): void {
    $challenge = googleChallenge();
    $claims = match ($state) {
        'audience'      => ['aud' => 'another-client'], 'issuer' => ['iss' => 'https://attacker.test'],
        'expiry'        => ['exp' => time() - 1], 'missing_expiry' => ['exp' => null],
        'future'        => ['iat' => time() + 3600], 'unverified' => ['email_verified' => false],
        'missing_email' => ['email' => null], 'invalid_email' => ['email' => 'invalid'],
        'subject'       => ['sub' => null], 'nonce' => ['nonce' => null], 'presenter' => ['azp' => 'another-client'],
        default         => [],
    };
    $credential = googleCredential($challenge, $claims);
    if ($state === 'signature') {
        $parts = explode('.', $credential);
        $parts[2] = ($parts[2][0] === 'a' ? 'b' : 'a').substr($parts[2], 1);
        $credential = implode('.', $parts);
    } elseif ($state === 'malformed') {
        $credential = 'not-a-jwt';
    }
    $this->postJson('/v1/auth/google-challenge-verifications', ['challenge_id' => $challenge['challenge_id'], 'credential' => $credential])
        ->assertUnprocessable()->assertJsonPath('errors.type', 'GoogleAuthException');
    expect(User::query()->count())->toBe(0)->and(ExternalIdentity::query()->count())->toBe(0)->and(PersonalAccessToken::query()->count())->toBe(0);
})->with(['audience', 'issuer', 'expiry', 'missing_expiry', 'future', 'unverified', 'missing_email', 'invalid_email', 'subject', 'nonce', 'presenter', 'signature', 'malformed']);

it('rejects wrong expired and consumed nonce challenges', function (string $state): void {
    $challenge = googleChallenge();
    $credential = googleCredential($challenge, $state === 'wrong_nonce' ? ['nonce' => 'wrong'] : []);
    if ($state === 'expired') {
        DB::table('iam_google_auth_challenges')->where('id', $challenge['challenge_id'])->update(['expires_at' => now()->subSecond()]);
    } elseif ($state === 'consumed') {
        $this->postJson('/v1/auth/google-challenge-verifications', ['challenge_id' => $challenge['challenge_id'], 'credential' => $credential])->assertOk();
    }
    $this->postJson('/v1/auth/google-challenge-verifications', ['challenge_id' => $challenge['challenge_id'], 'credential' => $credential])
        ->assertUnprocessable()->assertJsonPath('errors.type', 'GoogleAuthException');
    expect(PersonalAccessToken::query()->count())->toBe($state === 'consumed' ? 1 : 0);
})->with(['wrong_nonce', 'expired', 'consumed']);

it('binds a pending challenge to the verified identity', function (): void {
    User::factory()->create(['email' => 'new.google@gmail.com']);
    $challenge = googleChallenge();
    $this->postJson('/v1/auth/google-challenge-verifications', ['challenge_id' => $challenge['challenge_id'], 'credential' => googleCredential($challenge)])
        ->assertOk()->assertJsonPath('status', 'email_verification_required');
    $this->postJson('/v1/auth/google-challenge-verifications', [
        'challenge_id' => $challenge['challenge_id'], 'credential' => googleCredential($challenge, ['sub' => 'other-subject']),
    ])->assertUnprocessable();
    expect(ExternalIdentity::query()->count())->toBe(0);
});

it('blocks deleted accounts with and without a linked Google identity', function (bool $linked): void {
    $user = User::factory()->create(['email' => 'new.google@gmail.com']);
    if ($linked) {
        ExternalIdentity::factory()->create(['user_id' => $user->id, 'subject' => '100000000000000000001']);
    }
    $user->delete();
    googleSignIn()->assertUnprocessable();
    expect(PersonalAccessToken::query()->count())->toBe(0)->and(User::withTrashed()->count())->toBe(1);
})->with([true, false]);

it('requires the current account and a recent OTP session for linking', function (string $state): void {
    $user = User::factory()->create(['email' => $state === 'other_account' ? 'other@gmail.com' : 'new.google@gmail.com']);
    $token = $user->createToken('Client', ['*'], now()->addDay());
    $token->accessToken->update(['metadata' => ['session' => ['authentication_method' => $state === 'google_session' ? 'google' : 'email_otp']]]);
    if ($state === 'old_session') {
        $token->accessToken->forceFill(['created_at' => now()->subMinutes(11)])->save();
    } elseif ($state === 'already_linked') {
        ExternalIdentity::factory()->create(['user_id' => $user->id, 'subject' => 'another-subject']);
    }
    $challenge = googleChallenge();
    $this->withToken($token->plainTextToken)->postJson('/v1/auth/google-identities', [
        'challenge_id' => $challenge['challenge_id'], 'credential' => googleCredential($challenge),
    ])->assertUnprocessable()->assertJsonPath('errors.type', 'GoogleAuthException');
    expect(ExternalIdentity::query()->count())->toBe($state === 'already_linked' ? 1 : 0);
})->with(['other_account', 'old_session', 'google_session', 'already_linked']);

it('rejects browser origins outside the frontend and non JSON submissions', function (): void {
    currentTestCase()->withHeader('Origin', 'https://attacker.test')->postJson('/v1/auth/google-challenges')->assertUnprocessable();
    expect(DB::table('iam_google_auth_challenges')->count())->toBe(0);
    currentTestCase()->withHeader('Origin', 'https://app.example.test')->postJson('/v1/auth/google-challenges')->assertOk();
    currentTestCase()->post('/v1/auth/google-challenges')->assertUnprocessable();
});

it('caches the Google certificates across distinct authentications', function (): void {
    googleSignIn()->assertOk();
    googleSignIn(['sub' => 'another-subject', 'email' => 'other.google@gmail.com'])->assertOk();
    Http::assertSentCount(1);
});

it('allows frontend preflight requests with bearer headers and rejects unrelated origins', function (): void {
    currentTestCase()->options('/v1/auth/google-challenge-verifications', [], [
        'Origin'                         => 'https://app.example.test', 'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'Content-Type,Authorization',
    ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'https://app.example.test');
    currentTestCase()->options('/v1/auth/google-challenge-verifications', [], [
        'Origin' => 'https://attacker.test', 'Access-Control-Request-Method' => 'POST',
    ])->assertHeader('Access-Control-Allow-Origin', 'https://app.example.test');
});

it('fails cleanly when Google is not configured or cannot provide verification keys', function (bool $configured): void {
    if (!$configured) {
        config(['services.google.client_id' => null]);
        $this->postJson('/v1/auth/google-challenges')->assertUnprocessable();
    } else {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([AccessToken::FEDERATED_SIGNON_CERT_URL => Http::response([], 503)]);
        googleSignIn()->assertUnprocessable();
    }
    expect(User::query()->count())->toBe(0)->and(PersonalAccessToken::query()->count())->toBe(0);
})->with([true, false]);

it('creates an organization for a Google session without email registration and provisions working permissions', function (): void {
    $permission = Permission::factory()->create(['name' => 'iam_role.list']);
    foreach (SysRole::cases() as $systemRole) {
        $role = Role::factory()->create(['name' => $systemRole->value, 'team_id' => null, 'is_builtin' => true]);
        $role->givePermissionTo($permission);
    }
    $login = googleSignIn()->assertOk();
    $user = User::query()->findOrFail($login->json('data.user.id'));
    $this->withToken($login->json('data.access_token'))->postJson('/v1/auth/organizations', [
        'name'     => ' Google Organization ', 'currency_code' => 'xof', 'timezone' => 'Africa/Porto-Novo',
        'owner_id' => (string) Str::uuid7(), 'email' => 'attacker@test.example',
    ])->assertCreated()->assertJsonMissingPath('data.user');
    $organization = Organization::query()->where('owner_id', $user->id)->firstOrFail();
    $assignment = MemberRole::query()->where('organization_id', $organization->id)->firstOrFail();
    expect($organization->name)->toBe('Google Organization')->and($organization->functional_currency_code)->toBe('XOF')
        ->and($organization->settings->timezone)->toBe('Africa/Porto-Novo')
        ->and(DB::table('iam_organization_registration_tokens')->count())->toBe(0);
    $this->getJson('/v1/auth/me')->assertOk()->assertJsonPath('data.member_roles.0.id', $assignment->id);
    $this->postJson('/v1/auth/switch-member-role', ['member_role_id' => $assignment->id])->assertOk();
    $this->getJson('/v1/auth/current-permissions')->assertOk()->assertJsonPath('data.0.name', 'iam_role.list');
});

it('accepts an invitation for the Google account and uses the current offered roles', function (): void {
    $organization = Organization::factory()->create();
    $oldRole = Role::factory()->create(['team_id' => $organization->id, 'is_builtin' => false]);
    $role = Role::factory()->create(['team_id' => $organization->id, 'is_builtin' => false]);
    $token = Str::random(64);
    $invitation = Invitation::factory()->create(['organization_id' => $organization->id, 'email' => 'new.google@gmail.com', 'token_hash' => hash('sha256', $token)]);
    $invitation->roles()->syncWithPivotValues([$oldRole->id], ['organization_id' => $organization->id]);
    $login = googleSignIn()->assertOk();
    $invitation->roles()->syncWithPivotValues([$role->id], ['organization_id' => $organization->id]);
    $this->withToken($login->json('data.access_token'))->postJson('/v1/auth/invitations/accept', ['token' => $token])->assertCreated();
    $member = OrganizationMember::query()->where('organization_id', $organization->id)->where('user_id', $login->json('data.user.id'))->firstOrFail();
    $assignment = MemberRole::query()->where('organization_id', $organization->id)->where('member_id', $member->id)->firstOrFail();
    expect($assignment->role_id)->toBe($role->id)->and($invitation->fresh()->accepted_at)->not->toBeNull();
    $this->postJson('/v1/auth/switch-member-role', ['member_role_id' => $assignment->id])->assertOk();
    setPermissionsTeamId($organization->id);
    expect($assignment->fresh()->hasRole($role))->toBeTrue();
    $this->postJson('/v1/auth/invitations/accept', ['token' => $token])->assertUnprocessable();
});

it('rejects invitations addressed to a different account even if the payload spoofs its email', function (): void {
    $token = Str::random(64);
    $invitation = Invitation::factory()->create(['email' => 'different@gmail.com', 'token_hash' => hash('sha256', $token)]);
    $login = googleSignIn()->assertOk();
    $this->withToken($login->json('data.access_token'))->postJson('/v1/auth/invitations/accept', [
        'token' => $token, 'email' => $invitation->email,
    ])->assertUnprocessable();
    expect($invitation->fresh()->accepted_at)->toBeNull()->and(OrganizationMember::query()->count())->toBe(0);
});

it('requires authentication for account linking organization creation and account invitation acceptance', function (string $path): void {
    $this->postJson($path, [])->assertUnauthorized();
})->with(['/v1/auth/google-identities', '/v1/auth/organizations', '/v1/auth/invitations/accept']);

it('keeps Google available after the email authentication budget is exhausted', function (): void {
    currentTestCase()->withMiddleware(ThrottleRequests::class);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/v1/auth/email-challenges', [])->assertUnprocessable();
    }
    $this->postJson('/v1/auth/email-challenges', [])->assertStatus(429)->assertHeader('Retry-After');

    $challenge = googleChallenge();
    $this->postJson('/v1/auth/google-challenge-verifications', [
        'challenge_id' => $challenge['challenge_id'], 'credential' => googleCredential($challenge),
    ])->assertOk()->assertJsonPath('data.user.email', 'new.google@gmail.com');
});

it('still throttles excessive Google challenges without consuming the email budget', function (): void {
    currentTestCase()->withMiddleware(ThrottleRequests::class);

    for ($attempt = 0; $attempt < 20; $attempt++) {
        googleChallenge();
    }
    $this->postJson('/v1/auth/google-challenges')->assertStatus(429)->assertHeader('Retry-After');
    $this->postJson('/v1/auth/email-challenges', [])->assertUnprocessable();
});

it('requires email OTP before linking Google after automatic invitation sign in', function (): void {
    $user = User::factory()->create(['email' => 'invited.google@gmail.com']);
    $organization = Organization::factory()->create();
    $role = Role::factory()->create(['team_id' => $organization->id]);
    $token = Str::random(64);
    $invitation = Invitation::factory()->create([
        'organization_id' => $organization->id, 'email' => $user->email,
        'token_hash'      => hash('sha256', $token), 'expires_at' => now()->addDays(7),
    ]);
    $invitation->roles()->attach($role, ['organization_id' => $organization->id]);
    $accepted = $this->postJson('/v1/iam/invitations/accept', ['email' => $user->email, 'token' => $token])
        ->assertCreated()->assertJsonPath('data.user.id', $user->id);
    $challenge = googleChallenge();
    $credential = googleCredential($challenge, ['email' => $user->email]);
    $body = ['challenge_id' => $challenge['challenge_id'], 'credential' => $credential];
    $this->postJson('/v1/auth/google-challenge-verifications', $body)->assertOk()
        ->assertJsonPath('status', 'email_verification_required')->assertJsonPath('email', $user->email);
    $this->withToken($accepted->json('data.access_token'))->postJson('/v1/auth/google-identities', $body)
        ->assertUnprocessable()->assertJsonPath('message', __('iam::exceptions.google.recent_email_authentication_required'));
    expect(ExternalIdentity::query()->count())->toBe(0);
    $login = loginWithEmailCode($user->email)->assertOk();
    app('auth')->forgetGuards();
    $this->withToken($login->json('data.access_token'))->postJson('/v1/auth/google-identities', $body)->assertCreated();
    expect(ExternalIdentity::query()->where('user_id', $user->id)->count())->toBe(1);
});

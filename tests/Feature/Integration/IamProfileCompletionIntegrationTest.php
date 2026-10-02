<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lahatre\Iam\Models\Invitation;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    authContext()->clear();
    setPermissionsTeamId(null);
    app('auth')->forgetGuards();
    $this->withoutMiddleware(ThrottleRequests::class);
    Queue::fake();
});

it('blocks authenticated business routes before validation or organization resolution for incomplete profiles', function (string $method, string $path): void {
    $user = User::factory()->create(['last_name' => null]);
    $this->withToken($user->createToken('Incomplete')->plainTextToken)->json($method, $path)
        ->assertForbidden()->assertJsonPath('code', 'profile_incomplete');
    expect($user->tokens()->count())->toBe(1);
})->with([
    ['POST', '/v1/auth/organizations'],
    ['POST', '/v1/auth/invitations/accept'],
    ['POST', '/v1/auth/switch-member-role'],
    ['POST', '/v1/auth/google-identities'],
    ['GET', '/v1/auth/current-permissions'],
    ['GET', '/v1/iam/permissions'],
    ['GET', '/v1/organization/settings'],
]);

it('keeps account and session access available while completing names in separate requests', function (): void {
    $user = User::factory()->create(['first_name' => null, 'last_name' => null]);
    $this->withToken($user->createToken('Incomplete')->plainTextToken)->getJson('/v1/auth/me')
        ->assertOk()->assertJsonPath('data.profile_complete', false);
    $this->getJson('/v1/auth/sessions')->assertOk();
    $this->patchJson('/v1/auth/me', ['first_name' => ' Ada '])->assertNoContent();
    $this->getJson('/v1/auth/me')->assertOk()->assertJsonPath('data.first_name', 'Ada')->assertJsonPath('data.profile_complete', false);
    $this->postJson('/v1/auth/organizations')->assertForbidden()->assertJsonPath('code', 'profile_incomplete');
    $this->patchJson('/v1/auth/me', ['last_name' => ' Lovelace '])->assertNoContent();
    $this->getJson('/v1/auth/me')->assertOk()->assertJsonPath('data.profile_complete', true);
    $this->postJson('/v1/auth/organizations')->assertUnprocessable()->assertJsonValidationErrors(['name']);
    expect($user->tokens()->count())->toBe(1);
});

it('permits logout and individual or all session revocation for incomplete profiles', function (string $operation): void {
    $user = User::factory()->create(['first_name' => null]);
    $current = $user->createToken('Current');
    $other = $user->createToken('Other');
    $this->withToken($current->plainTextToken);
    if ($operation === 'logout') {
        $this->postJson('/v1/auth/logout')->assertOk();
        expect($user->tokens()->count())->toBe(1);
    } elseif ($operation === 'all') {
        $this->deleteJson('/v1/auth/sessions')->assertNoContent();
        expect($user->tokens()->count())->toBe(0);
    } else {
        $this->deleteJson('/v1/auth/sessions/'.$other->accessToken->id)->assertNoContent();
        expect($user->tokens()->count())->toBe(1);
    }
})->with(['logout', 'all', 'individual']);

it('treats absent and blank names as incomplete while preserving valid names', function (?string $firstName, ?string $lastName, bool $complete): void {
    $user = User::factory()->create(['first_name' => $firstName, 'last_name' => $lastName]);
    $this->withToken($user->createToken('Profile')->plainTextToken)->getJson('/v1/auth/me')
        ->assertOk()->assertJsonPath('data.profile_complete', $complete);
})->with([
    [null, 'Name', false], ['Name', null, false], ['', 'Name', false], ['Name', '   ', false], ['Ada', 'Lovelace', true],
]);

it('still returns unauthorized for missing credentials', function (): void {
    $this->postJson('/v1/auth/organizations')->assertUnauthorized();
});

it('keeps profile completion required while invitation links can issue an incomplete account session', function (string $flow): void {
    $user = User::factory()->create(['last_name' => null]);
    $token = Str::random(64);
    if ($flow === 'registration') {
        DB::table('iam_organization_registration_tokens')->insert([
            'id'         => (string) Str::uuid7(),
            'email'      => $user->email, 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHour(), 'created_at' => now(),
        ]);
        $this->postJson('/v1/auth/organization-registrations', [
            'email'        => $user->email, 'token' => $token,
            'organization' => ['name' => 'Blocked', 'currency_code' => 'XOF', 'timezone' => 'Africa/Porto-Novo'],
        ])->assertUnprocessable()->assertJsonPath('errors.type', 'EmailAccountException');
    } else {
        $organization = Organization::factory()->create();
        $invitation = Invitation::factory()->create([
            'organization_id' => $organization->id, 'email' => $user->email,
            'token_hash'      => hash('sha256', $token), 'expires_at' => now()->addHour(),
        ]);
        $role = Role::factory()->create(['team_id' => $organization->id]);
        $invitation->roles()->attach($role, ['organization_id' => $organization->id]);
        $accepted = $this->postJson('/v1/iam/invitations/accept', ['email' => $user->email, 'token' => $token])
            ->assertCreated()->assertJsonPath('data.user.profile_complete', false);
        expect($invitation->fresh()->accepted_at)->not->toBeNull();
        $this->withToken($accepted->json('data.access_token'))->postJson('/v1/auth/organizations')
            ->assertForbidden()->assertJsonPath('code', 'profile_incomplete');
    }
})->with(['registration', 'invitation']);

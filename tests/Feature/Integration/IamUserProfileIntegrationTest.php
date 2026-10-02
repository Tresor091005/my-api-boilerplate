<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lahatre\Iam\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    authContext()->clear();
    setPermissionsTeamId(null);
    app('auth')->forgetGuards();
    $this->withoutMiddleware(ThrottleRequests::class);
    Queue::fake();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
});

it('updates only supplied profile names and returns no content by default', function (string $field): void {
    $user = User::factory()->create(['first_name' => 'Original', 'last_name' => 'Profile']);
    $this->withToken($user->createToken('Client')->plainTextToken)
        ->patchJson('/v1/auth/me', [$field => '  Updated   Name  '])->assertNoContent();
    $this->getJson('/v1/auth/me')->assertOk()->assertJsonPath('data.'.$field, 'Updated Name');
    expect($user->fresh()->first_name)->toBe($field === 'first_name' ? 'Updated Name' : 'Original')
        ->and($user->fresh()->last_name)->toBe($field === 'last_name' ? 'Updated Name' : 'Profile');
})->with(['first_name', 'last_name']);

it('returns the updated profile when a resource response is requested', function (): void {
    $user = User::factory()->create();
    $this->withToken($user->createToken('Client')->plainTextToken)
        ->patchJson('/v1/auth/me?response=resource', ['first_name' => 'Ada', 'last_name' => 'Lovelace'])
        ->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonPath('data.first_name', 'Ada')
        ->assertJsonPath('data.last_name', 'Lovelace')->assertJsonPath('data.current_member_role_id', null)
        ->assertJsonPath('data.member_roles', []);
});

it('allows profile updates after the selected organization context becomes unavailable', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('Client');
    $token->accessToken->update(['metadata' => [
        'organization_id' => (string) Str::uuid7(), 'member_id' => (string) Str::uuid7(),
        'member_role_id'  => (string) Str::uuid7(), 'role_id' => (string) Str::uuid7(),
    ]]);
    $this->withToken($token->plainTextToken)->patchJson('/v1/auth/me', ['first_name' => 'Updated'])->assertNoContent();
    expect($user->fresh()->first_name)->toBe('Updated');
});

it('ignores immutable fields and cannot select another user through the payload', function (): void {
    $user = User::factory()->create();
    $foreign = User::factory()->create(['first_name' => 'Foreign']);
    $verifiedAt = $user->email_verified_at;
    $this->withToken($user->createToken('Client')->plainTextToken)->patchJson('/v1/auth/me', [
        'id'                => $foreign->id, 'first_name' => 'Updated', 'email' => 'changed@example.test',
        'email_verified_at' => null, 'deleted_at' => now()->toISOString(),
    ])->assertNoContent();
    $updated = $user->fresh();
    expect($updated->first_name)->toBe('Updated')->and($updated->email)->toBe($user->email)
        ->and($updated->email_verified_at->equalTo($verifiedAt))->toBeTrue()
        ->and($foreign->fresh()->first_name)->toBe('Foreign');
});

it('authorizes profile updates only for the account owner', function (): void {
    $user = User::factory()->create();
    $foreign = User::factory()->create();
    expect(Gate::forUser($user)->allows('update', $user))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $foreign))->toBeFalse();
});

it('rejects invalid supplied profile names', function (string $field, mixed $value): void {
    $user = User::factory()->create(['first_name' => 'Original', 'last_name' => 'Profile']);
    $this->withToken($user->createToken('Client')->plainTextToken)->patchJson('/v1/auth/me', [$field => $value])
        ->assertUnprocessable()->assertJsonValidationErrors([$field]);
    expect($user->fresh()->first_name)->toBe('Original')->and($user->fresh()->last_name)->toBe('Profile');
})->with([
    ['first_name', null], ['first_name', ''], ['first_name', '   '], ['first_name', 123],
    ['first_name', str_repeat('a', 101)], ['last_name', null], ['last_name', []], ['last_name', str_repeat('b', 101)],
]);

it('requires authentication to update a profile', function (): void {
    $this->patchJson('/v1/auth/me', ['first_name' => 'Ada'])->assertUnauthorized();
});

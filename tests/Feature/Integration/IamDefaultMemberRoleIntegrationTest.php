<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Http\Resources\UserResource;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
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

afterEach(function (): void {
    setPermissionsTeamId(null);
});

function defaultRoleAssignment(User $user, bool $builtin = false): MemberRole
{
    $organization = Organization::factory()->create(['owner_id' => $user->id]);
    $member = OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $role = Role::factory()->create(['team_id' => $builtin ? null : $organization->id, 'is_builtin' => $builtin]);
    $assignment = MemberRole::factory()->create([
        'organization_id' => $organization->id, 'member_id' => $member->id, 'role_id' => $role->id,
    ]);
    setPermissionsTeamId($organization->id);
    $assignment->syncRoles($role);
    setPermissionsTeamId(null);

    return $assignment;
}

it('stores one default across organizations and leaves the current session context unchanged', function (): void {
    $user = User::factory()->create();
    $first = defaultRoleAssignment($user);
    $second = defaultRoleAssignment($user);
    $token = $user->createToken('Client');
    $this->withToken($token->plainTextToken)->postJson('/v1/auth/switch-member-role', ['member_role_id' => $first->id])->assertOk();
    $this->patchJson('/v1/auth/me?response=resource', ['default_member_role_id' => $first->id])->assertOk()
        ->assertJsonPath('data.default_member_role_id', $first->id);
    $response = $this->patchJson('/v1/auth/me?response=resource', ['default_member_role_id' => $second->id])->assertOk()
        ->assertJsonPath('data.default_member_role_id', $second->id)->assertJsonPath('data.current_member_role_id', $first->id);
    $defaults = array_values(array_filter($response->json('data.member_roles'), fn (array $role): bool => $role['is_default']));
    expect(array_column($defaults, 'id'))->toBe([$second->id])
        ->and($user->fresh()->default_member_role_id)->toBe($second->id)
        ->and(PersonalAccessToken::query()->findOrFail($token->accessToken->id)->getMeta('member_role_id'))->toBe($first->id);
});

it('preserves an omitted preference and clears it with explicit null', function (): void {
    $user = User::factory()->create();
    $assignment = defaultRoleAssignment($user);
    $this->withToken($user->createToken('Client')->plainTextToken)
        ->patchJson('/v1/auth/me', ['default_member_role_id' => $assignment->id])->assertNoContent();
    $this->patchJson('/v1/auth/me', ['first_name' => 'Updated'])->assertNoContent();
    expect($user->fresh()->default_member_role_id)->toBe($assignment->id);
    $response = $this->patchJson('/v1/auth/me?response=resource', ['default_member_role_id' => null])->assertOk()
        ->assertJsonPath('data.default_member_role_id', null);
    expect(array_filter($response->json('data.member_roles'), fn (array $role): bool => $role['is_default']))->toBe([]);
});

it('exposes the preference on login without selecting an organization on the new token', function (): void {
    $user = User::factory()->create();
    $assignment = defaultRoleAssignment($user);
    $this->withToken($user->createToken('Client')->plainTextToken)
        ->patchJson('/v1/auth/me', ['default_member_role_id' => $assignment->id])->assertNoContent();
    $response = loginWithEmailCode($user->email)->assertOk()
        ->assertJsonPath('data.user.default_member_role_id', $assignment->id)
        ->assertJsonPath('data.user.member_roles.0.is_default', true)
        ->assertJsonPath('data.user.current_member_role_id', null);
    expect(PersonalAccessToken::findToken($response->json('data.access_token'))->getMeta('organization_id'))->toBeNull();
});

it('allows a builtin role already assigned to the account to be its default', function (): void {
    $user = User::factory()->create();
    $assignment = defaultRoleAssignment($user, builtin: true);
    $this->withToken($user->createToken('Client')->plainTextToken)
        ->patchJson('/v1/auth/me?response=resource', ['default_member_role_id' => $assignment->id])->assertOk()
        ->assertJsonPath('data.member_roles.0.is_default', true);
});

it('rejects unavailable defaults without changing the profile or existing preference', function (string $state): void {
    $user = User::factory()->create(['first_name' => 'Original']);
    $existing = defaultRoleAssignment($user);
    $candidate = defaultRoleAssignment($state === 'foreign_user' ? User::factory()->create() : $user);
    $this->withToken($user->createToken('Client')->plainTextToken)
        ->patchJson('/v1/auth/me', ['default_member_role_id' => $existing->id])->assertNoContent();
    $id = $candidate->id;
    match ($state) {
        'inactive_assignment'  => $candidate->update(['is_active' => false]),
        'inactive_member'      => $candidate->organizationMember->update(['is_active' => false]),
        'inactive_role'        => $candidate->role->update(['is_active' => false]),
        'deleted_assignment'   => $candidate->delete(),
        'deleted_member'       => $candidate->organizationMember->delete(),
        'deleted_role'         => $candidate->role->delete(),
        'deleted_organization' => Organization::query()->findOrFail($candidate->organization_id)->delete(),
        'wrong_guard'          => $candidate->role->update(['guard_name' => 'web']),
        'foreign_role'         => $candidate->role->update(['team_id' => Organization::factory()->create()->id]),
        'incoherent_member'    => $candidate->update(['organization_id' => Organization::factory()->create()->id]),
        default                => null,
    };
    if ($state === 'missing') {
        $id = (string) Str::uuid7();
    }
    $this->patchJson('/v1/auth/me', ['default_member_role_id' => $id, 'first_name' => 'Rejected'])
        ->assertUnprocessable()->assertJsonPath('errors.type', 'MemberRoleException');
    expect($user->fresh()->default_member_role_id)->toBe($existing->id)->and($user->fresh()->first_name)->toBe('Original');
    $response = $this->getJson('/v1/auth/me')->assertOk();
    expect(array_column($response->json('data.member_roles'), 'id'))
        ->toBe($state === 'missing' ? [$existing->id, $candidate->id] : [$existing->id]);
    $this->postJson('/v1/auth/switch-member-role', ['member_role_id' => $id])->assertNotFound();
})->with([
    'missing', 'foreign_user', 'inactive_assignment', 'inactive_member', 'inactive_role',
    'deleted_assignment', 'deleted_member', 'deleted_role', 'deleted_organization', 'wrong_guard', 'foreign_role', 'incoherent_member',
]);

it('does not flag an unavailable preference as default and retains it for possible reactivation', function (string $level): void {
    $user = User::factory()->create();
    $assignment = defaultRoleAssignment($user);
    $this->withToken($user->createToken('Client')->plainTextToken)
        ->patchJson('/v1/auth/me', ['default_member_role_id' => $assignment->id])->assertNoContent();
    $model = match ($level) {
        'member' => $assignment->organizationMember,
        'role'   => $assignment->role,
        default  => $assignment,
    };
    $model->update(['is_active' => false]);
    $response = $this->getJson('/v1/auth/me')->assertOk()->assertJsonPath('data.default_member_role_id', $assignment->id);
    expect($response->json('data.member_roles'))->toBe([]);
    $model->update(['is_active' => true]);
    $this->getJson('/v1/auth/me')->assertOk()->assertJsonPath('data.member_roles.0.is_default', true);
})->with(['assignment', 'member', 'role']);

it('does not flag soft deleted assignments or organizations as a default', function (string $level): void {
    $user = User::factory()->create();
    $assignment = defaultRoleAssignment($user);
    $this->withToken($user->createToken('Client')->plainTextToken)
        ->patchJson('/v1/auth/me', ['default_member_role_id' => $assignment->id])->assertNoContent();
    if ($level === 'organization') {
        Organization::query()->findOrFail($assignment->organization_id)->delete();
    } else {
        $assignment->delete();
    }
    $response = $this->getJson('/v1/auth/me')->assertOk();
    expect($response->json('data.member_roles'))->toBe([]);
})->with(['assignment', 'organization']);

it('renders preloaded member roles without queries and rejects an unresolved organization', function (): void {
    $user = User::factory()->create();
    $assignment = defaultRoleAssignment($user);
    $user->load(['organizationMemberships.organization', 'organizationMemberships.memberRoles.role']);
    $membership = $user->organizationMemberships->first();
    $memberRole = $membership->memberRoles->first();

    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $payload = (new UserResource($user))->response()->getData(true);
        expect(array_column($payload['data']['member_roles'], 'id'))->toBe([$assignment->id])
            ->and(DB::getQueryLog())->toBe([]);

        $membership->unsetRelation('organization');
        expect($memberRole->hasValidContextFor($user, $membership, $memberRole->role))->toBeFalse()
            ->and(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
});

it('clears the preference when its assignment is physically deleted', function (): void {
    $user = User::factory()->create();
    $assignment = defaultRoleAssignment($user);
    $this->withToken($user->createToken('Client')->plainTextToken)
        ->patchJson('/v1/auth/me', ['default_member_role_id' => $assignment->id])->assertNoContent();
    $assignment->forceDelete();
    app('auth')->forgetGuards();
    $this->getJson('/v1/auth/me')->assertOk()->assertJsonPath('data.default_member_role_id', null);
    expect($user->fresh()->default_member_role_id)->toBeNull();
});

it('validates the default member role identifier', function (mixed $id): void {
    $user = User::factory()->create();
    $this->withToken($user->createToken('Client')->plainTextToken)
        ->patchJson('/v1/auth/me', ['default_member_role_id' => $id])->assertUnprocessable()
        ->assertJsonValidationErrors(['default_member_role_id']);
})->with([['not-a-uuid'], [123], [[]]]);

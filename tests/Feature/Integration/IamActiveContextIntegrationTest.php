<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Auth\AuthContext;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    resetIamActiveContext();
    $this->withoutMiddleware(ThrottleRequests::class);
});

afterEach(function (): void {
    resetIamActiveContext();
});

function resetIamActiveContext(): void
{
    authContext()->clear();
    setPermissionsTeamId(null);
    app('auth')->forgetGuards();
}

/** @return array{organization: Organization, user: User, member: OrganizationMember, role: Role, assignment: MemberRole} */
function createIamActiveContext(
    ?User $user = null,
    ?Organization $organization = null,
    ?OrganizationMember $member = null,
    ?Role $role = null,
): array {
    $organization ??= Organization::factory()->create();
    $user ??= User::factory()->create();
    $member ??= OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $role ??= Role::factory()->create(['team_id' => $organization->id]);
    $assignment = MemberRole::factory()->create([
        'organization_id' => $organization->id, 'member_id' => $member->id, 'role_id' => $role->id,
    ]);
    setPermissionsTeamId($organization->id);
    $assignment->syncRoles($role);
    setPermissionsTeamId(null);

    return compact('organization', 'user', 'member', 'role', 'assignment');
}

/** @return array{organization_id: string, member_id: string, member_role_id: string, role_id: string} */
function iamActiveContextMetadata(MemberRole $assignment): array
{
    return [
        'organization_id' => $assignment->organization_id,
        'member_id'       => $assignment->member_id,
        'member_role_id'  => $assignment->id,
        'role_id'         => $assignment->role_id,
    ];
}

function useIamActiveContextToken(User $user, MemberRole $assignment): PersonalAccessToken
{
    $token = $user->createToken('active-context-test');
    $token->accessToken->update(['metadata' => iamActiveContextMetadata($assignment)]);
    resetIamActiveContext();
    currentTestCase()->withToken($token->plainTextToken);

    return PersonalAccessToken::query()->findOrFail($token->accessToken->getKey());
}

it('requires all three independent activation flags on every call with an existing token', function (bool $memberActive, bool $assignmentActive, bool $roleActive): void {
    $context = createIamActiveContext();
    $token = useIamActiveContextToken($context['user'], $context['assignment']);
    $this->getJson('/v1/auth/current-permissions')->assertOk();
    $context['member']->update(['is_active' => $memberActive]);
    $context['assignment']->update(['is_active' => $assignmentActive]);
    $context['role']->update(['is_active' => $roleActive]);

    $status = $memberActive && $assignmentActive && $roleActive ? 200 : 403;
    resetIamActiveContext();
    $this->getJson('/v1/auth/me')->assertOk();
    resetIamActiveContext();
    $this->getJson('/v1/auth/current-permissions')->assertStatus($status);

    expect($context['member']->fresh()->is_active)->toBe($memberActive)
        ->and($context['assignment']->fresh()->is_active)->toBe($assignmentActive)
        ->and($context['role']->fresh()->is_active)->toBe($roleActive);
    expect($token->fresh()->getAttribute('metadata'))->toMatchArray(iamActiveContextMetadata($context['assignment']));
})->with([
    'all active'                     => [true, true, true],
    'member inactive'                => [false, true, true],
    'assignment inactive'            => [true, false, true],
    'role inactive'                  => [true, true, false],
    'member and assignment inactive' => [false, false, true],
    'member and role inactive'       => [false, true, false],
    'assignment and role inactive'   => [true, false, false],
    'all inactive'                   => [false, false, false],
]);

it('refuses switching to an inactive context without changing token metadata', function (string $level): void {
    $context = createIamActiveContext();
    $context[$level]->update(['is_active' => false]);
    $token = $context['user']->createToken('unselected-context');
    $metadata = $token->accessToken->getAttribute('metadata');
    $this->withToken($token->plainTextToken)
        ->postJson('/v1/auth/switch-member-role', ['member_role_id' => $context['assignment']->id])->assertNotFound();
    expect(array_diff_key($token->accessToken->fresh()->getAttribute('metadata'), ['session' => true]))->toBe($metadata ?? []);
})->with(['member', 'assignment', 'role']);

it('rejects using or selecting contexts when any access record or the organization is soft deleted', function (string $level): void {
    $context = createIamActiveContext();
    useIamActiveContextToken($context['user'], $context['assignment']);
    $this->getJson('/v1/auth/current-permissions')->assertOk();
    $context[$level]->delete();
    resetIamActiveContext();
    $this->getJson('/v1/auth/current-permissions')->assertForbidden();
    resetIamActiveContext();
    $token = $context['user']->createToken('unselected-context');
    $this->withToken($token->plainTextToken)
        ->postJson('/v1/auth/switch-member-role', ['member_role_id' => $context['assignment']->id])->assertNotFound();
    expect(array_diff_key($token->accessToken->fresh()->getAttribute('metadata'), ['session' => true]))->toBe([]);
})->with(['member', 'assignment', 'role', 'organization']);

it('keeps another assignment usable when one assignment is deactivated', function (): void {
    $first = createIamActiveContext();
    $second = createIamActiveContext($first['user'], $first['organization'], $first['member']);
    $first['assignment']->update(['is_active' => false]);
    useIamActiveContextToken($first['user'], $first['assignment']);
    $this->getJson('/v1/auth/current-permissions')->assertForbidden();
    useIamActiveContextToken($second['user'], $second['assignment']);
    $this->getJson('/v1/auth/current-permissions')->assertOk();
    expect($first['member']->fresh()->is_active)->toBeTrue()
        ->and($first['role']->fresh()->is_active)->toBeTrue()
        ->and($second['assignment']->fresh()->is_active)->toBeTrue();
});

it('suspends every user of a role without modifying their memberships or assignments', function (): void {
    $first = createIamActiveContext();
    $second = createIamActiveContext(organization: $first['organization'], role: $first['role']);
    $first['role']->update(['is_active' => false]);
    foreach ([$first, $second] as $context) {
        useIamActiveContextToken($context['user'], $context['assignment']);
        $this->getJson('/v1/auth/current-permissions')->assertForbidden();
        expect($context['member']->fresh()->is_active)->toBeTrue()
            ->and($context['assignment']->fresh()->is_active)->toBeTrue();
    }
});

it('keeps the same user usable in another organization when a membership is deactivated', function (): void {
    $first = createIamActiveContext();
    $second = createIamActiveContext($first['user']);
    $first['member']->update(['is_active' => false]);
    useIamActiveContextToken($first['user'], $first['assignment']);
    $this->getJson('/v1/auth/current-permissions')->assertForbidden();
    useIamActiveContextToken($second['user'], $second['assignment']);
    $this->getJson('/v1/auth/current-permissions')->assertOk();
    expect($first['assignment']->fresh()->is_active)->toBeTrue()
        ->and($first['role']->fresh()->is_active)->toBeTrue()
        ->and($second['member']->fresh()->is_active)->toBeTrue();
});

it('keeps user-only authentication available without a selected organization', function (): void {
    $context = createIamActiveContext();
    $context['member']->update(['is_active' => false]);
    $login = loginWithEmailCode($context['user']->email)->assertOk();
    $this->withToken($login->json('data.access_token'))->getJson('/v1/auth/me')->assertOk();
    $this->getJson('/v1/auth/current-permissions')->assertForbidden();
});

it('returns unauthorized for missing invalid expired or revoked authentication on account and organization routes', function (string $reason): void {
    $context = createIamActiveContext();
    $access = $context['user']->createToken('authentication-status', ['*'], $reason === 'expired' ? now()->subMinute() : now()->addHour());
    $access->accessToken->update(['metadata' => iamActiveContextMetadata($context['assignment'])]);
    if ($reason === 'revoked') {
        $access->accessToken->delete();
    }
    $header = match ($reason) {
        'missing' => '',
        'invalid' => 'Bearer invalid-token',
        default   => 'Bearer '.$access->plainTextToken,
    };
    foreach (['/v1/auth/me', '/v1/auth/current-permissions'] as $path) {
        resetIamActiveContext();
        currentTestCase()->withHeader('Authorization', $header)->getJson($path)->assertUnauthorized();
    }
})->with(['missing', 'invalid', 'expired', 'revoked']);

it('clears a previous context before resolving user-only or invalid metadata', function (): void {
    $context = createIamActiveContext();
    $resolver = new AuthContext;
    $metadata = iamActiveContextMetadata($context['assignment']);
    $resolver->setContext($context['user'], $metadata);
    $resolver->setContext($context['user']);
    expect($resolver->user()?->id)->toBe($context['user']->id)
        ->and($resolver->organization())->toBeNull()
        ->and($resolver->member())->toBeNull()
        ->and($resolver->memberRole())->toBeNull()
        ->and($resolver->role())->toBeNull();
    $resolver->setContext($context['user'], $metadata);
    $context['role']->update(['is_active' => false]);
    expect(fn () => $resolver->setContext($context['user'], $metadata))->toThrow(AuthorizationException::class);
    expect($resolver->user())->toBeNull()
        ->and($resolver->organization())->toBeNull()
        ->and($resolver->member())->toBeNull()
        ->and($resolver->memberRole())->toBeNull()
        ->and($resolver->role())->toBeNull();
});

it('rejects a role from another organization or guard before using or selecting its context', function (string $mismatch): void {
    $context = createIamActiveContext();
    $context['role']->update($mismatch === 'organization'
        ? ['team_id' => Organization::factory()->create()->id]
        : ['guard_name' => 'web']);
    useIamActiveContextToken($context['user'], $context['assignment']);
    $this->getJson('/v1/auth/current-permissions')->assertForbidden();
    resetIamActiveContext();
    $token = $context['user']->createToken('unselected-context');
    $this->withToken($token->plainTextToken)
        ->postJson('/v1/auth/switch-member-role', ['member_role_id' => $context['assignment']->id])->assertNotFound();
    expect(array_diff_key($token->accessToken->fresh()->getAttribute('metadata'), ['session' => true]))->toBe([]);
})->with(['organization', 'guard']);

it('accepts active global built-in roles', function (): void {
    $role = Role::factory()->create(['team_id' => null, 'is_builtin' => true]);
    $context = createIamActiveContext(role: $role);
    useIamActiveContextToken($context['user'], $context['assignment']);
    $this->getJson('/v1/auth/current-permissions')->assertOk();
    expect(authContext()->role()?->id)->toBe($role->id);
});

it('exposes independent activation states when reading a member and its role assignments', function (): void {
    $actor = createIamActiveContext();
    $target = createIamActiveContext(organization: $actor['organization']);
    foreach (['member', 'assignment', 'role'] as $level) {
        $target[$level]->update(['is_active' => false]);
    }
    setPermissionsTeamId($actor['organization']->id);
    $actor['assignment']->givePermissionTo(Permission::factory()->create(['name' => 'iam_organization_member.retrieve']));
    useIamActiveContextToken($actor['user'], $actor['assignment']);
    $this->getJson('/v1/iam/organization-members/'.$target['member']->id.'?include=member_roles')
        ->assertOk()->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.member_roles.0.is_active', false)
        ->assertJsonPath('data.member_roles.0.role.is_active', false);
});

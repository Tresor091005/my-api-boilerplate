<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    authContext()->clear();
    setPermissionsTeamId(null);
    $this->withoutMiddleware(ThrottleRequests::class);
});

afterEach(function (): void {
    authContext()->clear();
    setPermissionsTeamId(null);
});

function iamActivationAccessToken(User $user, MemberRole $assignment): string
{
    $token = $user->createToken('activation-management');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $assignment->organization_id, 'member_id' => $assignment->member_id,
        'member_role_id'  => $assignment->id, 'role_id' => $assignment->role_id,
    ]]);

    return $token->plainTextToken;
}

function useIamActivationAccessToken(string $token): void
{
    authContext()->clear();
    setPermissionsTeamId(null);
    app('auth')->forgetGuards();
    currentTestCase()->withToken($token);
}

function iamActivationAssignment(OrganizationMember $member, Role $role, bool $isActive = true): MemberRole
{
    $assignment = MemberRole::factory()->create([
        'organization_id' => $member->organization_id, 'member_id' => $member->id,
        'role_id'         => $role->id, 'is_active' => $isActive,
    ]);
    $previousTeamId = getPermissionsTeamId();
    setPermissionsTeamId($member->organization_id);
    try {
        $assignment->syncRoles($role);
    } finally {
        setPermissionsTeamId($previousTeamId);
    }

    return $assignment;
}

/** @return array{organization: Organization, owner: OrganizationMember, member: OrganizationMember, role: Role, assignment: MemberRole, actorToken: string, targetToken: string} */
function iamActivationManagementContext(bool $authorized = true): array
{
    $organization = Organization::factory()->create();
    $actor = User::query()->findOrFail($organization->owner_id);
    $owner = OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $actor->id]);
    $actorRole = Role::factory()->create(['team_id' => $organization->id]);
    $actorAssignment = iamActivationAssignment($owner, $actorRole);
    if ($authorized) {
        $permissions = array_map(fn (string $name): Permission => Permission::factory()->create(['name' => $name]), [
            'iam_role.create', 'iam_role.update', 'iam_role.retrieve', 'iam_organization_member.update', 'iam_organization_member.retrieve',
        ]);
        $actorRole->givePermissionTo($permissions);
    }
    $member = OrganizationMember::factory()->create(['organization_id' => $organization->id]);
    $role = Role::factory()->create(['team_id' => $organization->id]);
    $assignment = iamActivationAssignment($member, $role);
    $actorToken = iamActivationAccessToken($actor, $actorAssignment);
    $targetToken = iamActivationAccessToken($member->user, $assignment);
    useIamActivationAccessToken($actorToken);

    return compact('organization', 'owner', 'member', 'role', 'assignment', 'actorToken', 'targetToken');
}

it('creates inactive roles and preserves activation when omitted from partial updates', function (): void {
    iamActivationManagementContext();
    $response = $this->postJson('/v1/iam/roles?response=resource', [
        'name' => 'Suspended role', 'permission_ids' => [], 'is_active' => false,
    ])->assertCreated()->assertJsonPath('data.is_active', false);
    $id = $response->json('data.id');
    $this->patchJson("/v1/iam/roles/{$id}?response=resource", ['description' => 'Updated description'])
        ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.description', 'Updated description');
    $this->putJson("/v1/iam/roles/{$id}?response=resource", ['is_active' => true])
        ->assertOk()->assertJsonPath('data.is_active', true);
});

it('changes only role activation and restores access on the same token after reactivation', function (): void {
    $context = iamActivationManagementContext();
    $permission = Permission::factory()->create(['name' => 'catalog_product.retrieve']);
    $context['role']->givePermissionTo($permission);
    foreach ([false, true] as $isActive) {
        useIamActivationAccessToken($context['actorToken']);
        $this->patchJson('/v1/iam/roles/'.$context['role']->id, ['is_active' => $isActive])->assertNoContent();
        useIamActivationAccessToken($context['targetToken']);
        $this->getJson('/v1/auth/current-permissions')->assertStatus($isActive ? 200 : 401);
        expect($context['member']->fresh()->is_active)->toBeTrue()
            ->and($context['assignment']->fresh()->is_active)->toBeTrue()
            ->and($context['role']->fresh()->is_active)->toBe($isActive)
            ->and($context['role']->permissions()->pluck('id')->all())->toBe([$permission->id]);
    }
    $this->getJson('/v1/auth/current-permissions')->assertJsonPath('data.0.id', $permission->id);
});

it('changes only member activation and always includes its user profile in resource responses', function (): void {
    $context = iamActivationManagementContext();
    foreach ([false, true] as $isActive) {
        useIamActivationAccessToken($context['actorToken']);
        $this->patchJson('/v1/iam/organization-members/'.$context['member']->id.'?response=resource&include=member_roles', ['is_active' => $isActive])
            ->assertOk()->assertJsonPath('data.is_active', $isActive)
            ->assertJsonPath('data.user.email', $context['member']->user->email)
            ->assertJsonMissingPath('data.user.id')->assertJsonMissingPath('data.user_id')
            ->assertJsonPath('data.member_roles.0.is_active', true)
            ->assertJsonPath('data.member_roles.0.role.is_active', true);
        useIamActivationAccessToken($context['targetToken']);
        $this->getJson('/v1/auth/current-permissions')->assertStatus($isActive ? 200 : 401);
        expect($context['assignment']->fresh()->is_active)->toBeTrue()->and($context['role']->fresh()->is_active)->toBeTrue();
    }
    useIamActivationAccessToken($context['actorToken']);
    $this->putJson('/v1/iam/organization-members/'.$context['member']->id, ['is_active' => true])->assertNoContent();
});

it('updates a complete assignment batch without deleting records or clearing Spatie roles', function (): void {
    $context = iamActivationManagementContext();
    $otherRole = Role::factory()->create(['team_id' => $context['organization']->id]);
    $other = iamActivationAssignment($context['member'], $otherRole);
    $otherToken = iamActivationAccessToken($context['member']->user, $other);
    $ids = [$context['assignment']->id, $other->id];
    $pivotCount = DB::table('iam_model_has_roles')->where('team_id', $context['organization']->id)
        ->where('model_type', $other->getMorphClass())->whereIn('model_id', $ids)->count();
    $url = '/v1/iam/organization-members/'.$context['member']->id.'/member-roles';
    foreach ([false, true] as $isActive) {
        useIamActivationAccessToken($context['actorToken']);
        $response = $this->patchJson($url.'?response=resource', ['member_role_ids' => $ids, 'is_active' => $isActive])
            ->assertOk()->assertJsonCount(2, 'data');
        foreach ($response->json('data') as $assignment) {
            expect($assignment['is_active'])->toBe($isActive)->and($assignment['role']['is_active'])->toBeTrue();
            expect($assignment['role'])->not->toHaveKey('permissions');
        }
        foreach ([$context['targetToken'], $otherToken] as $token) {
            useIamActivationAccessToken($token);
            $this->getJson('/v1/auth/current-permissions')->assertStatus($isActive ? 200 : 401);
        }
        expect($context['member']->fresh()->is_active)->toBeTrue()
            ->and(MemberRole::query()->where('organization_id', $context['organization']->id)->whereIn('id', $ids)->count())->toBe(2)
            ->and(DB::table('iam_model_has_roles')->where('team_id', $context['organization']->id)
                ->where('model_type', $other->getMorphClass())->whereIn('model_id', $ids)->count())->toBe($pivotCount);
    }
    useIamActivationAccessToken($context['actorToken']);
    $this->putJson($url, ['member_role_ids' => $ids, 'is_active' => true])->assertNoContent();
    $this->patchJson($url.'?response=resource&include=role.permissions', ['member_role_ids' => $ids, 'is_active' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('include');
});

it('creates suspended assignments with Spatie roles ready for reactivation', function (): void {
    $context = iamActivationManagementContext();
    $role = Role::factory()->create(['team_id' => $context['organization']->id]);
    $url = '/v1/iam/organization-members/'.$context['member']->id.'/member-roles';
    $response = $this->postJson($url.'?response=resource', ['role_ids' => [$role->id], 'is_active' => false])
        ->assertCreated()->assertJsonPath('data.0.is_active', false)->assertJsonPath('data.0.role.id', $role->id);
    $assignment = MemberRole::query()->findOrFail($response->json('data.0.id'));
    $targetToken = iamActivationAccessToken($context['member']->user, $assignment);
    useIamActivationAccessToken($targetToken);
    $this->getJson('/v1/auth/current-permissions')->assertUnauthorized();
    useIamActivationAccessToken($context['actorToken']);
    $this->patchJson($url, ['member_role_ids' => [$assignment->id], 'is_active' => true])->assertNoContent();
    useIamActivationAccessToken($targetToken);
    $this->getJson('/v1/auth/current-permissions')->assertOk();
});

it('requires authentication and the parent update ability for activation changes', function (string $kind): void {
    $context = iamActivationManagementContext(false);
    [$url, $payload] = match ($kind) {
        'role'        => ['/v1/iam/roles/'.$context['role']->id, ['is_active' => false]],
        'member'      => ['/v1/iam/organization-members/'.$context['member']->id, ['is_active' => false]],
        'assignments' => ['/v1/iam/organization-members/'.$context['member']->id.'/member-roles', [
            'member_role_ids' => [$context['assignment']->id], 'is_active' => false,
        ]],
        default => throw new InvalidArgumentException('Unsupported activation target.'),
    };
    $this->patchJson($url, $payload)->assertForbidden();
    useIamActivationAccessToken('invalid-token');
    $this->patchJson($url, $payload)->assertUnauthorized();
    expect($context['role']->fresh()->is_active)->toBeTrue()
        ->and($context['member']->fresh()->is_active)->toBeTrue()
        ->and($context['assignment']->fresh()->is_active)->toBeTrue();
})->with(['role', 'member', 'assignments']);

it('preserves built-in and foreign roles when activation is requested', function (string $kind): void {
    $context = iamActivationManagementContext();
    $role = Role::factory()->create($kind === 'builtin'
        ? ['team_id' => null, 'is_builtin' => true, 'name' => SysRole::Administrator->value]
        : ['team_id' => Organization::factory()->create()->id]);
    $this->patchJson('/v1/iam/roles/'.$role->id, ['is_active' => false])->assertForbidden();
    expect($role->fresh()->is_active)->toBeTrue();
})->with(['builtin', 'foreign']);

it('rejects foreign and deleted members on activation routes', function (): void {
    $context = iamActivationManagementContext();
    $foreign = OrganizationMember::factory()->create();
    $deleted = OrganizationMember::factory()->create(['organization_id' => $context['organization']->id]);
    $deleted->delete();
    $this->patchJson('/v1/iam/organization-members/'.$foreign->id, ['is_active' => false])->assertForbidden();
    $this->patchJson('/v1/iam/organization-members/'.$deleted->id, ['is_active' => false])->assertNotFound();
    expect($foreign->fresh()->is_active)->toBeTrue();
});

it('protects the owner membership and Administrator assignment before any batch change', function (): void {
    $context = iamActivationManagementContext();
    $adminRole = Role::factory()->create(['team_id' => null, 'is_builtin' => true, 'name' => SysRole::Administrator->value]);
    $admin = iamActivationAssignment($context['owner'], $adminRole);
    $otherRole = Role::factory()->create(['team_id' => $context['organization']->id]);
    $other = iamActivationAssignment($context['owner'], $otherRole);
    $this->patchJson('/v1/iam/organization-members/'.$context['owner']->id, ['is_active' => false])
        ->assertUnprocessable()->assertJsonPath('message', __('iam::exceptions.organization_member.owner'));
    $url = '/v1/iam/organization-members/'.$context['owner']->id.'/member-roles';
    $this->patchJson($url, ['member_role_ids' => [$other->id, $admin->id], 'is_active' => false])
        ->assertUnprocessable()->assertJsonPath('message', __('iam::exceptions.member_role.owner_administrator_protected'));
    expect($admin->fresh()->is_active)->toBeTrue()->and($other->fresh()->is_active)->toBeTrue()
        ->and($context['owner']->fresh()->is_active)->toBeTrue();
    $this->patchJson($url, ['member_role_ids' => [$other->id], 'is_active' => false])->assertNoContent();
    $this->patchJson($url, ['member_role_ids' => [$admin->id, $other->id], 'is_active' => true])->assertNoContent();
});

it('rejects an unavailable assignment atomically with the rest of its batch', function (string $kind): void {
    $context = iamActivationManagementContext();
    if ($kind === 'foreign' || $kind === 'other member') {
        $member = OrganizationMember::factory()->create($kind === 'other member' ? ['organization_id' => $context['organization']->id] : []);
        $invalid = MemberRole::factory()->create(['organization_id' => $member->organization_id, 'member_id' => $member->id]);
    } else {
        $role = Role::factory()->create(match ($kind) {
            'foreign role' => ['team_id' => Organization::factory()->create()->id],
            'wrong guard'  => ['team_id' => $context['organization']->id, 'guard_name' => 'web'],
            default        => ['team_id' => $context['organization']->id],
        });
        $invalid = MemberRole::factory()->create([
            'organization_id' => $context['organization']->id, 'member_id' => $context['member']->id, 'role_id' => $role->id,
        ]);
        if ($kind === 'deleted assignment') {
            $invalid->delete();
        }
        if ($kind === 'deleted role') {
            $role->delete();
        }
    }
    $this->patchJson('/v1/iam/organization-members/'.$context['member']->id.'/member-roles', [
        'member_role_ids' => [$context['assignment']->id, $invalid->id], 'is_active' => false,
    ])->assertUnprocessable();
    expect($context['assignment']->fresh()->is_active)->toBeTrue()->and($invalid->fresh()->is_active)->toBeTrue();
})->with(['foreign', 'other member', 'deleted assignment', 'foreign role', 'wrong guard', 'deleted role']);

it('validates required activation and complete assignment batches before mutation', function (array $payload, array $errors): void {
    $context = iamActivationManagementContext();
    $ids = [$context['assignment']->id];
    $this->patchJson('/v1/iam/organization-members/'.$context['member']->id.'/member-roles', [
        'member_role_ids' => $ids, ...$payload,
    ])->assertUnprocessable()->assertJsonValidationErrors($errors);
    expect($context['assignment']->fresh()->is_active)->toBeTrue();
})->with([
    'missing activation' => [[], ['is_active']],
    'null activation'    => [['is_active' => null], ['is_active']],
    'invalid activation' => [['is_active' => 'false'], ['is_active']],
    'empty batch'        => [['is_active' => false, 'member_role_ids' => []], ['member_role_ids']],
    'unknown assignment' => [['is_active' => false, 'member_role_ids' => [(string) Str::uuid7()]], ['member_role_ids.0']],
]);

it('validates activation booleans for roles and members', function (mixed $value): void {
    $context = iamActivationManagementContext();
    $this->patchJson('/v1/iam/roles/'.$context['role']->id, ['is_active' => $value])
        ->assertUnprocessable()->assertJsonValidationErrors('is_active');
    $this->patchJson('/v1/iam/organization-members/'.$context['member']->id, ['is_active' => $value])
        ->assertUnprocessable()->assertJsonValidationErrors('is_active');
})->with([[null], ['false'], [[]]]);

it('keeps activation controls independent when the other access levels remain suspended', function (): void {
    $context = iamActivationManagementContext();
    $context['member']->update(['is_active' => false]);
    $context['role']->update(['is_active' => false]);
    $context['assignment']->update(['is_active' => false]);
    $this->patchJson('/v1/iam/organization-members/'.$context['member']->id.'/member-roles', [
        'member_role_ids' => [$context['assignment']->id], 'is_active' => true,
    ])->assertNoContent();
    expect($context['member']->fresh()->is_active)->toBeFalse()
        ->and($context['role']->fresh()->is_active)->toBeFalse()
        ->and($context['assignment']->fresh()->is_active)->toBeTrue();
    useIamActivationAccessToken($context['targetToken']);
    $this->getJson('/v1/auth/current-permissions')->assertUnauthorized();
    useIamActivationAccessToken($context['actorToken']);
    $this->patchJson('/v1/iam/organization-members/'.$context['member']->id, ['is_active' => true])->assertNoContent();
    useIamActivationAccessToken($context['targetToken']);
    $this->getJson('/v1/auth/current-permissions')->assertUnauthorized();
    useIamActivationAccessToken($context['actorToken']);
    $this->patchJson('/v1/iam/roles/'.$context['role']->id, ['is_active' => true])->assertNoContent();
    useIamActivationAccessToken($context['targetToken']);
    $this->getJson('/v1/auth/current-permissions')->assertOk();
});

it('rejects duplicate or oversized assignment batches', function (int $size): void {
    $context = iamActivationManagementContext();
    $this->patchJson('/v1/iam/organization-members/'.$context['member']->id.'/member-roles', [
        'member_role_ids' => array_fill(0, $size, $context['assignment']->id), 'is_active' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors($size > 100 ? 'member_role_ids' : 'member_role_ids.0');
    expect($context['assignment']->fresh()->is_active)->toBeTrue();
})->with([2, 101]);

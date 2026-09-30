<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setPermissionsTeamId(null);
    $this->withoutMiddleware(ThrottleRequests::class);
});

afterEach(function (): void {
    setPermissionsTeamId(null);
});

/** @return array{organization: Organization, member: OrganizationMember} */
function authenticatedMemberRoleManagementContext(bool $canUpdate = true): array
{
    $organization = Organization::factory()->create();
    $actor = User::factory()->create();
    $actorMember = OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $actor->id]);
    $actorRole = Role::factory()->create(['team_id' => $organization->id]);
    $actorAssignment = MemberRole::factory()->create([
        'organization_id' => $organization->id, 'member_id' => $actorMember->id, 'role_id' => $actorRole->id,
    ]);
    setPermissionsTeamId($organization->id);
    if ($canUpdate) {
        $actorAssignment->givePermissionTo(Permission::factory()->create(['name' => 'iam_organization_member.update']));
    }
    $token = $actor->createToken('member-role-management');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $organization->id, 'member_id' => $actorMember->id,
        'member_role_id'  => $actorAssignment->id, 'role_id' => $actorRole->id,
    ]]);
    currentTestCase()->withToken($token->plainTextToken);
    $member = OrganizationMember::factory()->create(['organization_id' => $organization->id]);

    return compact('organization', 'member');
}

function memberRoleManagementUrl(OrganizationMember $member): string
{
    return "/v1/iam/organization-members/{$member->id}/member-roles";
}

function existingManagedMemberRole(OrganizationMember $member, ?Role $role = null): MemberRole
{
    $role ??= Role::factory()->create(['team_id' => $member->organization_id]);

    $assignment = MemberRole::factory()->create([
        'organization_id' => $member->organization_id, 'member_id' => $member->id, 'role_id' => $role->id,
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

it('requires authentication and member update permission for both batch actions', function (): void {
    $member = OrganizationMember::factory()->create();
    $this->postJson(memberRoleManagementUrl($member), ['role_ids' => []])->assertUnauthorized();
    $this->deleteJson(memberRoleManagementUrl($member), ['member_role_ids' => []])->assertUnauthorized();
    $context = authenticatedMemberRoleManagementContext(false);
    $role = Role::factory()->create(['team_id' => $context['organization']->id]);
    $assignment = existingManagedMemberRole($context['member']);
    $this->postJson(memberRoleManagementUrl($context['member']), ['role_ids' => [$role->id]])->assertForbidden();
    $this->deleteJson(memberRoleManagementUrl($context['member']), ['member_role_ids' => [$assignment->id]])->assertForbidden();
    expect($assignment->fresh()->trashed())->toBeFalse();
});

it('creates a batch with required roles and synchronizes effective team permissions', function (): void {
    $context = authenticatedMemberRoleManagementContext();
    $roles = Role::factory()->count(2)->create(['team_id' => $context['organization']->id]);
    $permission = Permission::factory()->create(['name' => 'catalog_product.list']);
    $roles->first()->givePermissionTo($permission);
    $response = $this->postJson(memberRoleManagementUrl($context['member']).'?response=resource', [
        'role_ids' => $roles->modelKeys(),
    ])->assertCreated()->assertJsonCount(2, 'data')->assertJsonMissingPath('data.0.role.permissions')
        ->assertJsonMissingPath('data.0.organization_id');
    expect(collect($response->json('data'))->pluck('role.id')->all())->toEqualCanonicalizing($roles->modelKeys());
    $assignments = MemberRole::query()->where('member_id', $context['member']->id)->get();
    foreach ($assignments as $assignment) {
        expect($assignment->roles()->pluck('iam_roles.id')->all())->toBe([$assignment->role_id]);
    }
    $withPermission = $assignments->firstWhere('role_id', $roles->first()->id);
    expect($withPermission->hasPermissionTo($permission))->toBeTrue();
    $user = User::query()->findOrFail($context['member']->user_id);
    $token = $user->createToken('new-role');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $context['organization']->id, 'member_id' => $context['member']->id,
        'member_role_id'  => $withPermission->id, 'role_id' => $withPermission->role_id,
    ]]);
    app('auth')->forgetGuards();
    $this->withToken($token->plainTextToken)->getJson('/v1/auth/current-permissions')->assertOk()
        ->assertJsonPath('data.0.name', $permission->name);
});

it('defaults to no content and always loads the nested role in an explicit resource', function (): void {
    $context = authenticatedMemberRoleManagementContext();
    $roles = Role::factory()->count(2)->create(['team_id' => $context['organization']->id]);
    $this->postJson(memberRoleManagementUrl($context['member']), ['role_ids' => [$roles[0]->id]])->assertNoContent();
    $this->postJson(memberRoleManagementUrl($context['member']).'?response=resource', ['role_ids' => [$roles[1]->id]])
        ->assertCreated()->assertJsonPath('data.0.role.id', $roles[1]->id)->assertJsonMissingPath('data.0.role.permissions');
    $this->postJson(memberRoleManagementUrl($context['member']).'?response=resource&include=role.permissions', ['role_ids' => [$roles[1]->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('include');
});

it('rejects unavailable role batches without adding any attribution', function (string $kind): void {
    $context = authenticatedMemberRoleManagementContext();
    $valid = Role::factory()->create(['team_id' => $context['organization']->id]);
    $invalid = match ($kind) {
        'builtin'              => Role::factory()->create(['team_id' => null, 'is_builtin' => true, 'name' => SysRole::Administrator->value]),
        'builtin organization' => Role::factory()->create(['team_id' => $context['organization']->id, 'is_builtin' => true]),
        'foreign'              => Role::factory()->create(['team_id' => Organization::factory()->create()->id]),
        'wrong guard'          => Role::factory()->create(['team_id' => $context['organization']->id, 'guard_name' => 'web']),
        'deleted'              => Role::factory()->create(['team_id' => $context['organization']->id]),
        default                => throw new InvalidArgumentException('Unsupported unavailable role kind.'),
    };
    if ($kind === 'deleted') {
        $invalid->delete();
    }
    $this->postJson(memberRoleManagementUrl($context['member']), ['role_ids' => [$valid->id, $invalid->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('role_ids.1');
    expect($context['member']->memberRoles()->count())->toBe(0);
})->with(['builtin', 'builtin organization', 'foreign', 'wrong guard', 'deleted']);

it('rejects active duplicates atomically and issues a new attribution after soft deletion', function (): void {
    $context = authenticatedMemberRoleManagementContext();
    $existing = existingManagedMemberRole($context['member']);
    $newRole = Role::factory()->create(['team_id' => $context['organization']->id]);
    $this->postJson(memberRoleManagementUrl($context['member']), ['role_ids' => [$newRole->id, $existing->role_id]])
        ->assertUnprocessable()->assertJsonPath('message', __('iam::exceptions.member_role.already_assigned'));
    expect($context['member']->memberRoles()->count())->toBe(1);
    $this->deleteJson(memberRoleManagementUrl($context['member']), ['member_role_ids' => [$existing->id]])->assertNoContent();
    $response = $this->postJson(memberRoleManagementUrl($context['member']).'?response=resource', ['role_ids' => [$existing->role_id]])
        ->assertCreated();
    expect($response->json('data.0.id'))->not->toBe($existing->id)
        ->and(MemberRole::withTrashed()->where('member_id', $context['member']->id)->count())->toBe(2);
});

it('removes multiple attributions down to zero without deleting the member or their account', function (): void {
    $context = authenticatedMemberRoleManagementContext();
    $first = existingManagedMemberRole($context['member']);
    $second = existingManagedMemberRole($context['member']);
    $this->deleteJson(memberRoleManagementUrl($context['member']), ['member_role_ids' => [$first->id, $second->id]])->assertNoContent();
    expect($first->fresh()->trashed())->toBeTrue()->and($second->fresh()->trashed())->toBeTrue()
        ->and($first->fresh()->roles()->exists())->toBeFalse()->and($second->fresh()->roles()->exists())->toBeFalse()
        ->and($context['member']->fresh()->trashed())->toBeFalse()->and($context['member']->memberRoles()->count())->toBe(0)
        ->and(User::query()->whereKey($context['member']->user_id)->exists())->toBeTrue();
});

it('rejects a whole removal batch containing another member attribution', function (): void {
    $context = authenticatedMemberRoleManagementContext();
    $own = existingManagedMemberRole($context['member']);
    $otherMember = OrganizationMember::factory()->create(['organization_id' => $context['organization']->id]);
    $other = existingManagedMemberRole($otherMember);
    $this->deleteJson(memberRoleManagementUrl($context['member']), ['member_role_ids' => [$own->id, $other->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('member_role_ids.1');
    expect($own->fresh()->trashed())->toBeFalse()->and($other->fresh()->trashed())->toBeFalse();
    expect($own->fresh()->roles()->pluck('iam_roles.id')->all())->toBe([$own->role_id])
        ->and($other->fresh()->roles()->pluck('iam_roles.id')->all())->toBe([$other->role_id]);
});

it('protects the owner Administrator attribution and rolls back the whole removal batch', function (): void {
    $context = authenticatedMemberRoleManagementContext();
    $owner = OrganizationMember::factory()->create([
        'organization_id' => $context['organization']->id, 'user_id' => $context['organization']->owner_id,
    ]);
    $admin = Role::factory()->create(['team_id' => null, 'is_builtin' => true, 'name' => SysRole::Administrator->value]);
    $adminAssignment = existingManagedMemberRole($owner, $admin);
    $custom = existingManagedMemberRole($owner);
    $this->deleteJson(memberRoleManagementUrl($owner), ['member_role_ids' => [$custom->id, $adminAssignment->id]])
        ->assertUnprocessable()->assertJsonPath('message', __('iam::exceptions.member_role.owner_administrator_protected'));
    expect($custom->fresh()->trashed())->toBeFalse()->and($adminAssignment->fresh()->trashed())->toBeFalse();
    expect($custom->fresh()->roles()->pluck('iam_roles.id')->all())->toBe([$custom->role_id])
        ->and($adminAssignment->fresh()->roles()->pluck('iam_roles.id')->all())->toBe([$admin->id]);
    $this->deleteJson(memberRoleManagementUrl($owner), ['member_role_ids' => [$custom->id]])->assertNoContent();
    expect($adminAssignment->fresh()->trashed())->toBeFalse();
    expect($custom->fresh()->roles()->exists())->toBeFalse()
        ->and($adminAssignment->fresh()->roles()->pluck('iam_roles.id')->all())->toBe([$admin->id]);
});

it('can withdraw a legacy Administrator attribution from a member who is not the owner', function (): void {
    $context = authenticatedMemberRoleManagementContext();
    $admin = Role::factory()->create(['team_id' => null, 'is_builtin' => true, 'name' => SysRole::Administrator->value]);
    $assignment = existingManagedMemberRole($context['member'], $admin);
    $this->deleteJson(memberRoleManagementUrl($context['member']), ['member_role_ids' => [$assignment->id]])->assertNoContent();
});

it('rejects foreign or deleted parent memberships', function (): void {
    $context = authenticatedMemberRoleManagementContext();
    $role = Role::factory()->create(['team_id' => $context['organization']->id]);
    $foreign = OrganizationMember::factory()->create();
    $this->postJson(memberRoleManagementUrl($foreign), ['role_ids' => [$role->id]])->assertForbidden();
    $context['member']->delete();
    $this->postJson(memberRoleManagementUrl($context['member']), ['role_ids' => [$role->id]])->assertNotFound();
});

it('validates nonempty distinct role id lists for both actions', function (string $method, string $field, array $ids): void {
    $context = authenticatedMemberRoleManagementContext();
    if ($ids === ['duplicate']) {
        $id = $method === 'POST'
            ? Role::factory()->create(['team_id' => $context['organization']->id])->id
            : existingManagedMemberRole($context['member'])->id;
        $ids = [$id, $id];
    }
    $response = $method === 'POST'
        ? $this->postJson(memberRoleManagementUrl($context['member']), [$field => $ids])
        : $this->deleteJson(memberRoleManagementUrl($context['member']), [$field => $ids]);
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors($ids === [] ? $field : $field.'.0');
})->with([
    ['POST', 'role_ids', []],
    ['DELETE', 'member_role_ids', []],
    ['POST', 'role_ids', ['invalid']],
    ['DELETE', 'member_role_ids', ['invalid']],
    ['POST', 'role_ids', ['duplicate']],
    ['DELETE', 'member_role_ids', ['duplicate']],
]);

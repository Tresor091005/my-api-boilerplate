<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
});

/** @return array{organization: Organization, user: User, member: OrganizationMember, memberRole: MemberRole} */
function authenticatedRoleContext(array $abilities = ['list', 'retrieve', 'create', 'update', 'delete']): array
{
    $test = currentTestCase();
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $member = OrganizationMember::factory()->create([
        'organization_id' => $organization->id,
        'user_id'         => $user->id,
    ]);
    setPermissionsTeamId($organization->id);
    $activeRole = Role::factory()->create(['team_id' => $organization->id]);
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $organization->id,
        'member_id'       => $member->id,
        'role_id'         => $activeRole->id,
    ]);

    foreach ($abilities as $ability) {
        $permission = Permission::factory()->create(['name' => "iam_role.{$ability}"]);
        $memberRole->givePermissionTo($permission);
    }

    $token = $user->createToken('role-api-test');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $activeRole->id,
    ]]);
    $test->withToken($token->plainTextToken);

    return compact('organization', 'user', 'member', 'memberRole');
}

it('lists organization roles and system roles with permissions, excluding other organizations', function (): void {
    $context = authenticatedRoleContext();
    $organization = $context['organization'];
    $builtin = Role::factory()->create(['name' => 'administrator', 'team_id' => null, 'is_builtin' => true]);
    $own = Role::factory()->create(['name' => 'editor', 'team_id' => $organization->id]);
    $other = Role::factory()->create(['name' => 'outsider', 'team_id' => Organization::factory()->create()->id]);
    $permission = Permission::factory()->create(['name' => 'catalog_product.list']);
    $own->givePermissionTo($permission);

    $this->getJson('/v1/iam/roles?per_page=20')
        ->assertOk()
        ->assertJsonMissingPath('data.0.permissions');

    $response = $this->getJson('/v1/iam/roles?per_page=20&include=permissions')->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($builtin->id, $own->id)
        ->not->toContain($other->id);
    expect(collect($response->json('data'))->firstWhere('id', $own->id)['permissions'][0]['name'])
        ->toBe($permission->name);
    $response->assertJsonPath('meta.per_page', 20);

    $this->getJson("/v1/iam/roles/{$builtin->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.permissions');
    $this->getJson("/v1/iam/roles/{$builtin->id}?include=permissions")
        ->assertOk()
        ->assertJsonPath('data.is_builtin', true)
        ->assertJsonPath('data.permissions', []);
    $this->getJson("/v1/iam/roles/{$other->id}")->assertForbidden();
});

it('creates a tenant role and synchronizes its permissions', function (): void {
    $organization = authenticatedRoleContext()['organization'];
    $permission = Permission::factory()->create(['name' => 'catalog_product.list']);

    $this->postJson('/v1/iam/roles?response=resource&include=permissions', [
        'name'           => ' Sales Manager ',
        'description'    => ' Manages sales ',
        'permission_ids' => [$permission->id],
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Sales Manager')
        ->assertJsonPath('data.description', 'Manages sales')
        ->assertJsonMissingPath('data.team_id')
        ->assertJsonPath('data.is_builtin', false)
        ->assertJsonPath('data.permissions.0.id', $permission->id);

    $role = Role::query()->where('team_id', $organization->id)->where('name', 'Sales Manager')->firstOrFail();
    expect($role->guard_name)->toBe('sanctum')
        ->and($role->team_id)->toBe($organization->id)
        ->and($role->permissions->pluck('id')->all())->toBe([$permission->id]);
});

it('updates a tenant role and distinguishes omitted permissions from an empty list', function (): void {
    $organization = authenticatedRoleContext()['organization'];
    $role = Role::factory()->create(['team_id' => $organization->id]);
    $permission = Permission::factory()->create(['name' => 'catalog_product.list']);
    $role->givePermissionTo($permission);

    $this->patchJson("/v1/iam/roles/{$role->id}", ['name' => 'Revised role', 'description' => 'Revised'])
        ->assertNoContent();
    expect($role->fresh()->name)->toBe('Revised role');
    expect($role->fresh()->permissions->pluck('id')->all())->toBe([$permission->id]);

    $this->patchJson("/v1/iam/roles/{$role->id}?response=resource&include=permissions", ['permission_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.permissions', []);
    expect($role->fresh()->permissions)->toHaveCount(0);

    $this->patchJson("/v1/iam/roles/{$role->id}?response=resource", ['description' => null])
        ->assertOk()
        ->assertJsonMissingPath('data.permissions');
    expect($role->fresh()->description)->toBeNull()
        ->and($role->fresh()->name)->toBe('Revised role');
});

it('protects system roles and roles from another organization', function (): void {
    authenticatedRoleContext();
    $builtin = Role::factory()->create(['team_id' => null, 'is_builtin' => true]);
    $other = Role::factory()->create(['team_id' => Organization::factory()->create()->id]);

    $this->patchJson("/v1/iam/roles/{$builtin->id}", ['name' => 'Changed'])->assertForbidden();
    $this->deleteJson("/v1/iam/roles/{$builtin->id}")->assertForbidden();
    $this->patchJson("/v1/iam/roles/{$other->id}", ['name' => 'Changed'])->assertForbidden();
    $this->deleteJson("/v1/iam/roles/{$other->id}")->assertForbidden();
    expect($builtin->fresh()->name)->not->toBe('Changed');
});

it('rejects invalid names and permission identifiers', function (): void {
    $organization = authenticatedRoleContext()['organization'];
    Role::factory()->create(['name' => 'administrator', 'team_id' => null, 'is_builtin' => true]);
    Role::factory()->create(['name' => 'Team editor', 'team_id' => Organization::factory()->create()->id]);
    $webPermission = Permission::factory()->create(['name' => 'other_guard.list', 'guard_name' => 'web']);

    $this->postJson('/v1/iam/roles', ['name' => 'administrator', 'permission_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
    $this->postJson('/v1/iam/roles', ['name' => 'New', 'permission_ids' => [$webPermission->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permission_ids.0');

    $this->postJson('/v1/iam/roles', ['name' => 'Team editor', 'permission_ids' => []])
        ->assertNoContent();
    expect(Role::query()->where('team_id', $organization->id)->where('name', 'Team editor')->exists())->toBeTrue();
});

it('rejects deleting a role with an active member assignment', function (): void {
    $context = authenticatedRoleContext();
    $organization = $context['organization'];
    $assigned = Role::factory()->create(['team_id' => $organization->id]);
    MemberRole::factory()->create([
        'organization_id' => $organization->id,
        'member_id'       => $context['member']->id,
        'role_id'         => $assigned->id,
    ]);

    $this->deleteJson("/v1/iam/roles/{$assigned->id}")->assertUnprocessable();
    expect($assigned->fresh()->deleted_at)->toBeNull();
});

it('soft-deletes a role when its only member assignment was deleted', function (): void {
    $context = authenticatedRoleContext();
    $organization = $context['organization'];
    $role = Role::factory()->create(['team_id' => $organization->id, 'name' => 'Former editor']);
    $role->givePermissionTo(Permission::factory()->create(['name' => 'catalog_product.list']));
    $assignment = MemberRole::factory()->create([
        'organization_id' => $organization->id,
        'member_id'       => $context['member']->id,
        'role_id'         => $role->id,
    ]);
    $assignment->delete();

    $this->deleteJson("/v1/iam/roles/{$role->id}")->assertNoContent();
    expect(Role::query()->find($role->id))->toBeNull()
        ->and(Role::withTrashed()->findOrFail($role->id)->deleted_at)->not->toBeNull()
        ->and(DB::table('iam_role_has_permissions')->where('role_id', $role->id)->exists())->toBeTrue()
        ->and(MemberRole::withTrashed()->whereKey($assignment->id)->exists())->toBeTrue();

    $this->getJson("/v1/iam/roles/{$role->id}")->assertNotFound();
    $this->postJson('/v1/iam/roles', ['name' => 'Former editor', 'permission_ids' => []])->assertNoContent();
    expect(Role::query()->where('team_id', $organization->id)->where('name', 'Former editor')->count())->toBe(1);
});

it('requires the matching role capability for management', function (): void {
    $organization = authenticatedRoleContext([])['organization'];
    $role = Role::factory()->create(['team_id' => $organization->id]);

    $this->getJson('/v1/iam/roles')->assertForbidden();
    $this->getJson("/v1/iam/roles/{$role->id}")->assertForbidden();
    $this->postJson('/v1/iam/roles', ['name' => 'Denied', 'permission_ids' => []])->assertForbidden();
    $this->patchJson("/v1/iam/roles/{$role->id}", ['name' => 'Denied'])->assertForbidden();
    $this->deleteJson("/v1/iam/roles/{$role->id}")->assertForbidden();
});

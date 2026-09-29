<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
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

it('requires an authenticated organization role to list permissions', function (): void {
    $this->getJson('/v1/iam/permissions')->assertUnauthorized();

    $user = User::factory()->create();
    $this->withToken($user->createToken('permissions-test')->plainTextToken)
        ->getJson('/v1/iam/permissions')
        ->assertUnauthorized();
});

it('lists every permission for the active guard, including unassigned permissions', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $member = OrganizationMember::factory()->create([
        'organization_id' => $organization->id,
        'user_id'         => $user->id,
    ]);
    $role = Role::factory()->create();
    $memberRole = MemberRole::factory()->create([
        'organization_id' => $organization->id,
        'member_id'       => $member->id,
        'role_id'         => $role->id,
    ]);
    setPermissionsTeamId($organization->id);
    $token = $user->createToken('permissions-test');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);

    $first = Permission::factory()->create(['name' => 'catalog_product.list']);
    $second = Permission::factory()->create(['name' => 'catalog_product.retrieve']);
    $listPermission = Permission::factory()->create(['name' => 'iam_permission.list']);
    Permission::factory()->create(['name' => 'other_guard.list', 'guard_name' => 'web']);

    $this->withToken($token->plainTextToken)
        ->getJson('/v1/iam/permissions')
        ->assertForbidden();

    $memberRole->givePermissionTo($listPermission);

    $this->getJson('/v1/iam/permissions')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.0.name', $first->name)
        ->assertJsonPath('data.0.title', $first->title)
        ->assertJsonPath('data.0.description', $first->description)
        ->assertJsonPath('data.1.id', $second->id)
        ->assertJsonPath('data.2.id', $listPermission->id)
        ->assertJsonMissingPath('data.0.guard_name');
});

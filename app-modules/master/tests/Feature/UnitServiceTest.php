<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 1. Setup organization
    $this->organization = Organization::factory()->create([
        'name' => 'Test Org',
    ]);

    setPermissionsTeamId($this->organization->id);

    // 2. Setup user and membership
    $this->user = User::factory()->create();

    $this->member = OrganizationMember::create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);

    // 3. Setup Role and Permissions
    $this->role = Role::query()->firstOrCreate([
        'name'       => 'admin',
        'guard_name' => 'sanctum',
    ]);

    $this->memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $this->role->id,
    ]);

    $permissions = [
        'units.list',
        'units.create',
        'units.update',
    ];

    collect($permissions)->each(function (string $permissionName): void {
        Permission::query()->firstOrCreate([
            'name'       => $permissionName,
            'guard_name' => 'sanctum',
        ]);
    });

    $this->memberRole->givePermissionTo($permissions);

    // 4. Create token with metadata
    $token = $this->user->createToken('test_token');
    $token->accessToken->update([
        'metadata' => [
            'organization_id' => $this->organization->id,
            'member_id'       => $this->member->id,
            'member_role_id'  => $this->memberRole->id,
            'role_id'         => $this->role->id,
        ],
    ]);

    $this->withToken($token->plainTextToken);
});

it('lists both system units and tenant units but excludes other tenant units', function (): void {
    $otherOrg = Organization::factory()->create();

    // 1. System Unit (organization_id is NULL) - use 'a' prefix to stay on first page
    $systemGroup = UnitGroup::factory()->create([
        'name'            => 'system-mass-test',
        'organization_id' => null,
    ]);
    Unit::factory()->create([
        'code'            => 'a-kg-sys',
        'group_id'        => $systemGroup->id,
        'organization_id' => null,
    ]);

    // 2. Our Tenant Unit
    $ourGroup = UnitGroup::factory()->create([
        'name'            => 'our-custom-mass-test',
        'organization_id' => $this->organization->id,
    ]);
    Unit::factory()->create([
        'code'            => 'a-our-kg',
        'group_id'        => $ourGroup->id,
        'organization_id' => $this->organization->id,
    ]);

    // 3. Other Tenant Unit
    $otherGroup = UnitGroup::factory()->create([
        'name'            => 'other-custom-mass-test',
        'organization_id' => $otherOrg->id,
    ]);
    Unit::factory()->create([
        'code'            => 'a-other-kg',
        'group_id'        => $otherGroup->id,
        'organization_id' => $otherOrg->id,
    ]);

    app(UnitCache::class)->rewarmUnits();

    // List should see System + Our Tenant, but NOT Other Tenant
    $this->getJson('/v1/master/units')
        ->assertOk()
        ->assertJsonFragment(['code' => 'a-kg-sys'])
        ->assertJsonFragment(['code' => 'a-our-kg'])
        ->assertJsonMissing(['code' => 'a-other-kg']);
});

it('syncs unit groups and units strictly for the current tenant', function (): void {
    // 1. Create new group and units (should auto-assign organization_id)
    $this->postJson('/v1/master/units/sync', [
        'group_name' => 'new-tenant-group',
        'units'      => [
            ['name' => 'Unit 1', 'symbol' => 'U1', 'ratio' => 1],
        ],
    ])->assertOk();

    $group = UnitGroup::where('name', 'new-tenant-group')->first();
    expect($group->organization_id)->toBe($this->organization->id);

    $unit = $group->units()->first();
    expect($unit->organization_id)->toBe($this->organization->id);

    // 2. Prevent syncing/updating a system group (should fail validation)
    $systemGroup = UnitGroup::factory()->create([
        'name'            => 'system-group-test',
        'organization_id' => null,
    ]);

    $this->postJson('/v1/master/units/sync', [
        'group_id'   => $systemGroup->id,
        'group_name' => 'hacked-name',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['group_id']);

    // 3. Prevent syncing/updating another tenant's group
    $otherOrg = Organization::factory()->create();
    $otherGroup = UnitGroup::factory()->create([
        'name'            => 'other-tenant-group-test',
        'organization_id' => $otherOrg->id,
    ]);

    $this->postJson('/v1/master/units/sync', [
        'group_id'   => $otherGroup->id,
        'group_name' => 'hacked-other-name',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['group_id']);
});

it('verifies that unit codes are unique across the entire system', function (): void {
    $otherOrg = Organization::factory()->create();

    // Create a unit in another organization with code 'unique-code'
    $otherGroup = UnitGroup::factory()->create(['organization_id' => $otherOrg->id]);
    Unit::factory()->create([
        'code'            => 'unique-code',
        'group_id'        => $otherGroup->id,
        'organization_id' => $otherOrg->id,
    ]);

    // Try to create a unit in our organization with the same name
    // The handle generator should detect the code collision and append a suffix.

    $this->postJson('/v1/master/units/sync', [
        'group_name' => 'our-group-unique',
        'units'      => [
            ['name' => 'unique-code', 'symbol' => 'U1', 'ratio' => 1],
        ],
    ])->assertOk();

    $unit = Unit::where('name', 'unique-code')->where('organization_id', $this->organization->id)->first();
    expect($unit->code)->toBe('unique-code-1');
});

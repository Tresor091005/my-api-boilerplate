<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Models\Category;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
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
        'categories.list',
        'categories.retrieve',
        'categories.create',
        'categories.update',
        'categories.delete',
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

it('manages categories through the api resource flow and scopes by organization', function (): void {
    $otherOrg = Organization::factory()->create();

    // Category in our organization
    $category = Category::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'Electronics',
    ]);

    // Category in another organization
    $otherCategory = Category::factory()->create([
        'organization_id' => $otherOrg->id,
        'name'            => 'Other Org Category',
    ]);

    // 1. List - should only see our organization's category
    $this->getJson('/v1/catalog/categories')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Electronics')
        ->assertJsonPath('data.0.id', $category->id)
        ->assertJsonMissing(['id' => $otherCategory->id]);

    // 2. Show - our organization
    $this->getJson("/v1/catalog/categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('name', 'Electronics');

    // 3. Show - other organization (Forbidden)
    $this->getJson("/v1/catalog/categories/{$otherCategory->id}")
        ->assertForbidden();

    // 4. Create - should automatically assign our organization_id
    $createdResponse = $this->postJson('/v1/catalog/categories', [
        'name'      => 'Smartphones',
        'is_active' => true,
    ]);

    $createdResponse->assertCreated()
        ->assertJsonPath('name', 'Smartphones');

    $createdCategoryId = (string) $createdResponse->json('id');
    $createdCategory = Category::find($createdCategoryId);

    expect($createdCategory->organization_id)->toBe($this->organization->id);

    // 5. Update - our organization
    $this->putJson("/v1/catalog/categories/{$category->id}", [
        'name'      => 'Gadgets',
        'is_active' => true,
    ])->assertOk()
        ->assertJsonPath('name', 'Gadgets');

    // 6. Update - other organization (Forbidden)
    $this->putJson("/v1/catalog/categories/{$otherCategory->id}", [
        'name'      => 'Hacked',
        'is_active' => true,
    ])->assertForbidden();

    // 7. Delete - our organization
    $this->deleteJson("/v1/catalog/categories/{$createdCategoryId}")
        ->assertNoContent();

    expect(Category::whereKey($createdCategoryId)->exists())->toBeFalse();

    // 8. Delete - other organization (Forbidden)
    $this->deleteJson("/v1/catalog/categories/{$otherCategory->id}")
        ->assertForbidden();

    expect(Category::whereKey($otherCategory->id)->exists())->toBeTrue();
});

it('validates category creation', function (): void {
    $this->postJson('/v1/catalog/categories', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('requires permissions for category actions', function (): void {
    $this->memberRole->revokePermissionTo([
        'categories.list',
        'categories.retrieve',
        'categories.create',
        'categories.update',
        'categories.delete',
    ]);

    $category = Category::factory()->create(['organization_id' => $this->organization->id]);

    $this->getJson('/v1/catalog/categories')->assertForbidden();
    $this->getJson("/v1/catalog/categories/{$category->id}")->assertForbidden();
    $this->postJson('/v1/catalog/categories', ['name' => 'Test'])->assertForbidden();
    $this->putJson("/v1/catalog/categories/{$category->id}", ['name' => 'Updated'])->assertForbidden();
    $this->deleteJson("/v1/catalog/categories/{$category->id}")->assertForbidden();
});

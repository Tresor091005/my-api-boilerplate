<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Models\Product;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
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
        'products.list',
        'products.retrieve',
        'products.create',
        'products.update',
        'products.delete',
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

it('manages products through the api resource flow and scopes by organization', function (): void {
    $otherOrg = Organization::factory()->create();

    // Product in our organization
    $product = Product::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'iPhone 15 Pro',
    ]);

    // Product in another organization
    $otherProduct = Product::factory()->create([
        'organization_id' => $otherOrg->id,
        'name'            => 'Other Org Product',
    ]);

    // 1. List - should only see our organization's product
    $this->getJson('/v1/catalog/products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'iPhone 15 Pro')
        ->assertJsonMissing(['id' => $otherProduct->id]);

    // 2. Show - our organization
    $this->getJson("/v1/catalog/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('name', 'iPhone 15 Pro');

    // 3. Show - other organization (Forbidden)
    $this->getJson("/v1/catalog/products/{$otherProduct->id}")
        ->assertForbidden();

    // 4. Create - should automatically assign our organization_id
    $unitGroup = UnitGroup::factory()->create();
    Unit::factory()->create([
        'group_id' => $unitGroup->id,
        'ratio'    => 1,
    ]);

    $createdResponse = $this->postJson('/v1/catalog/products', [
        'name'      => 'Samsung Galaxy S24',
        'is_active' => true,
        'variants'  => [
            [
                'sku'                 => 'SGS24-123',
                'unit_group_id'       => $unitGroup->id,
                'should_manage_stock' => true,
                'is_active'           => true,
                'options'             => [
                    ['name' => 'Color', 'value' => 'Black'],
                ],
            ],
        ],
    ]);

    if ($createdResponse->status() !== 201) {
        $createdResponse->dump();
    }

    $createdResponse->assertCreated()
        ->assertJsonPath('name', 'Samsung Galaxy S24');

    $createdProductId = (string) $createdResponse->json('id');
    $createdProduct = Product::find($createdProductId);

    expect($createdProduct->organization_id)->toBe($this->organization->id);

    // 5. Update - our organization
    $this->putJson("/v1/catalog/products/{$product->id}", [
        'name'      => 'iPhone 15 Pro Updated',
        'is_active' => true,
    ])->assertOk()
        ->assertJsonPath('name', 'iPhone 15 Pro Updated');

    // 6. Update - other organization (Forbidden)
    $this->putJson("/v1/catalog/products/{$otherProduct->id}", [
        'name'      => 'Hacked',
        'is_active' => true,
    ])->assertForbidden();

    // 7. Delete - our organization
    $this->deleteJson("/v1/catalog/products/{$createdProductId}")
        ->assertNoContent();

    expect(Product::whereKey($createdProductId)->exists())->toBeFalse();

    // 8. Delete - other organization (Forbidden)
    $this->deleteJson("/v1/catalog/products/{$otherProduct->id}")
        ->assertForbidden();

    expect(Product::whereKey($otherProduct->id)->exists())->toBeTrue();
});

it('validates product creation', function (): void {
    $this->postJson('/v1/catalog/products', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'variants']);
});

it('requires permissions for product actions', function (): void {
    $this->memberRole->revokePermissionTo([
        'products.list',
        'products.retrieve',
        'products.create',
        'products.update',
        'products.delete',
    ]);

    $product = Product::factory()->create(['organization_id' => $this->organization->id]);

    $this->getJson('/v1/catalog/products')->assertForbidden();
    $this->getJson("/v1/catalog/products/{$product->id}")->assertForbidden();
    $this->postJson('/v1/catalog/products', ['name' => 'Test'])->assertForbidden();
    $this->putJson("/v1/catalog/products/{$product->id}", ['name' => 'Updated'])->assertForbidden();
    $this->deleteJson("/v1/catalog/products/{$product->id}")->assertForbidden();
});

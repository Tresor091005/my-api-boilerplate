<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\Option\OptionService;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Inventory\Contracts\InventoryInterface;
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
        'product_variants.list',
        'product_variants.retrieve',
        'product_variants.create',
        'product_variants.update',
        'product_variants.delete',
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

it('manages product variants through the api resource flow and scopes by organization', function (): void {
    $otherOrg = Organization::factory()->create();

    // Our Product
    $product = Product::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'iPhone 15 Pro',
    ]);

    // Other Product
    $otherProduct = Product::factory()->create([
        'organization_id' => $otherOrg->id,
        'name'            => 'Other Org Product',
    ]);

    // 5. Create - should automatically assign our organization_id
    $unitGroup = UnitGroup::firstOrCreate(['name' => 'mass']);
    Unit::firstOrCreate(
        ['code' => 'kg'],
        [
            'name'     => 'kilogram',
            'symbol'   => 'kg',
            'ratio'    => 1,
            'group_id' => $unitGroup->id,
        ]
    );

    app(UnitCache::class)->rewarmUnits();

    // Ensure option exists
    app(OptionService::class)->getOrCreate(collect([
        ['name' => 'color', 'value' => 'white'],
    ]));

    // Our Variant
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->organization->id,
        'product_id'      => $product->id,
        'sku'             => 'IP15P-BLA-128',
        'unit_group_id'   => $unitGroup->id,
    ]);

    // Create another variant to allow deletion of others
    ProductVariant::factory()->create([
        'organization_id' => $this->organization->id,
        'product_id'      => $product->id,
        'unit_group_id'   => $unitGroup->id,
    ]);

    // Ensure inventory item exists
    app(InventoryInterface::class)->createItem($variant);

    // Other Variant
    $otherUnitGroup = UnitGroup::firstOrCreate(['name' => 'other-mass']);
    Unit::firstOrCreate(
        ['code' => 'okg'],
        [
            'name'     => 'other-kilogram',
            'symbol'   => 'okg',
            'ratio'    => 1,
            'group_id' => $otherUnitGroup->id,
        ]
    );

    $otherVariant = ProductVariant::factory()->create([
        'organization_id' => $otherOrg->id,
        'product_id'      => $otherProduct->id,
        'sku'             => 'OTHER-SKU',
        'unit_group_id'   => $otherUnitGroup->id,
    ]);

    app(UnitCache::class)->rewarmUnits();

    // 1. List - should only see our organization's variant (we have 2 for this product)
    $this->getJson("/v1/catalog/products/{$product->id}/variants")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.sku', 'IP15P-BLA-128')
        ->assertJsonMissing(['sku' => 'OTHER-SKU']);

    // 2. List - other organization product (Not Found)
    $this->getJson("/v1/catalog/products/{$otherProduct->id}/variants")
        ->assertNotFound();

    // 3. Show - our organization
    $this->getJson("/v1/catalog/products/{$product->id}/variants/{$variant->id}")
        ->assertOk()
        ->assertJsonPath('sku', 'IP15P-BLA-128');

    // 4. Show - other organization (Forbidden because of policy)
    $this->getJson("/v1/catalog/products/{$otherProduct->id}/variants/{$otherVariant->id}")
        ->assertForbidden();

    // 5. Create - should automatically assign our organization_id
    $createdResponse = $this->postJson("/v1/catalog/products/{$product->id}/variants", [
        'variants' => [
            [
                'sku'                 => 'NEW-VARIANT-SKU',
                'unit_group_id'       => $unitGroup->id,
                'should_manage_stock' => true,
                'is_active'           => true,
                'options'             => [
                    ['name' => 'color', 'value' => 'white'],
                ],
            ],
        ],
    ]);

    $createdResponse->assertCreated()
        ->assertJsonCount(1)
        ->assertJsonPath('0.sku', 'NEW-VARIANT-SKU');

    $createdVariantId = (string) $createdResponse->json('0.id');
    $createdVariant = ProductVariant::find($createdVariantId);

    expect($createdVariant->organization_id)->toBe($this->organization->id);

    // 6. Update - our organization
    $this->patchJson("/v1/catalog/products/{$product->id}/variants/{$variant->id}", [
        'sku' => 'UPDATED-SKU',
    ])->assertOk()
        ->assertJsonPath('sku', 'UPDATED-SKU');

    // 7. Update - other organization (Forbidden)
    $this->patchJson("/v1/catalog/products/{$otherProduct->id}/variants/{$otherVariant->id}", [
        'sku' => 'HACKED-SKU',
    ])->assertForbidden();

    // 8. Delete - our organization
    $this->deleteJson("/v1/catalog/products/{$product->id}/variants/{$createdVariantId}")
        ->assertNoContent();

    expect(ProductVariant::whereKey($createdVariantId)->exists())->toBeFalse();

    // 9. Delete - other organization (Forbidden)
    $this->deleteJson("/v1/catalog/products/{$otherProduct->id}/variants/{$otherVariant->id}")
        ->assertForbidden();

    expect(ProductVariant::whereKey($otherVariant->id)->exists())->toBeTrue();
});

it('validates variant creation', function (): void {
    // Product in our organization
    $product = Product::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->postJson("/v1/catalog/products/{$product->id}/variants", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['variants']);
});

it('requires permissions for variant actions', function (): void {
    $this->memberRole->revokePermissionTo([
        'product_variants.list',
        'product_variants.retrieve',
        'product_variants.create',
        'product_variants.update',
        'product_variants.delete',
    ]);

    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->organization->id,
        'product_id'      => $product->id,
    ]);

    $this->getJson("/v1/catalog/products/{$product->id}/variants")->assertForbidden();
    $this->getJson("/v1/catalog/products/{$product->id}/variants/{$variant->id}")->assertForbidden();
    $this->postJson("/v1/catalog/products/{$product->id}/variants", ['variants' => []])->assertForbidden();
    $this->patchJson("/v1/catalog/products/{$product->id}/variants/{$variant->id}", ['sku' => 'TEST'])->assertForbidden();
    $this->deleteJson("/v1/catalog/products/{$product->id}/variants/{$variant->id}")->assertForbidden();
});

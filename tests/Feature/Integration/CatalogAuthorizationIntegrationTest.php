<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create([
        'name' => 'Integration Test Org',
    ]);

    setPermissionsTeamId($this->organization->id);

    $this->user = User::factory()->create();
    $this->member = OrganizationMember::create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);

    $this->role = Role::query()->firstOrCreate([
        'name'       => 'integration-admin',
        'guard_name' => 'sanctum',
    ]);

    $this->memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $this->role->id,
    ]);

    $permissions = [
        'catalog_category.list', 'catalog_category.retrieve', 'catalog_category.create', 'catalog_category.update', 'catalog_category.delete',
        'catalog_option.list', 'catalog_option.retrieve', 'catalog_option.create', 'catalog_option.update', 'catalog_option.delete',
        'catalog_option_value.list', 'catalog_option_value.retrieve', 'catalog_option_value.create', 'catalog_option_value.update', 'catalog_option_value.delete',
        'catalog_product.list', 'catalog_product.retrieve', 'catalog_product.create', 'catalog_product.update', 'catalog_product.delete',
        'catalog_product_variant.list', 'catalog_product_variant.retrieve', 'catalog_product_variant.create', 'catalog_product_variant.update', 'catalog_product_variant.delete',
    ];

    collect($permissions)->each(function (string $permissionName): void {
        Permission::query()->firstOrCreate([
            'name'       => $permissionName,
            'guard_name' => 'sanctum',
        ]);
    });
    $this->memberRole->givePermissionTo($permissions);

    $token = $this->user->createToken('integration-token');
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

it('enforces catalog permissions at http layer', function (): void {
    $category = Category::factory()->create(['organization_id' => $this->organization->id]);
    $option = Option::factory()->create(['organization_id' => $this->organization->id]);
    $optionValue = OptionValue::factory()->create([
        'organization_id' => $this->organization->id,
        'option_id'       => $option->id,
    ]);
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $catalogItem = CatalogItem::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $variant = ProductVariant::factory()->forCatalogItem($catalogItem)->create([
        'product_id' => $product->id,
    ]);
    $unitGroup = UnitGroup::factory()->create();

    $this->memberRole->revokePermissionTo(Permission::query()->pluck('name')->all());

    $this->getJson('/v1/catalog/categories')->assertForbidden();
    $this->postJson('/v1/catalog/categories', [
        'name'      => 'x',
        'is_active' => true,
    ])->assertForbidden();
    $this->getJson("/v1/catalog/options/{$option->id}/values")->assertForbidden();
    $this->postJson('/v1/catalog/products', [
        'name'     => 'x',
        'variants' => [[
            'unit_group_id' => $unitGroup->id,
            'options'       => [['name' => 'color', 'value' => 'blue']],
        ]],
    ])->assertForbidden();
    $this->getJson("/v1/catalog/products/{$product->id}/variants")->assertForbidden();
    $this->patchJson("/v1/catalog/products/{$product->id}/variants/{$variant->id}", ['sku' => 'ANY'])->assertForbidden();
    $this->deleteJson("/v1/catalog/options/{$option->id}/values/{$optionValue->id}")->assertForbidden();
    $this->deleteJson("/v1/catalog/categories/{$category->id}")->assertForbidden();
});

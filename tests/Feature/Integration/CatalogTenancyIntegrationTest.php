<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
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
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create([
        'name' => 'Catalog Tenant A',
    ]);
    $this->otherOrganization = Organization::factory()->create([
        'name' => 'Catalog Tenant B',
    ]);

    setPermissionsTeamId($this->organization->id);

    $this->user = User::factory()->create();
    $this->member = OrganizationMember::create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);

    $this->role = Role::query()->firstOrCreate([
        'name'       => 'catalog-tenant-admin',
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

    $token = $this->user->createToken('tenant-token');
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

it('scopes tenant-owned catalog relations across lazy eager aggregate and pivot queries', function (): void {
    $product = Product::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $otherProduct = Product::factory()->create([
        'organization_id' => $this->otherOrganization->id,
    ]);
    $category = Category::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $otherCategory = Category::factory()->create([
        'organization_id' => $this->otherOrganization->id,
    ]);

    $product->categories()->attach($category, [
        'organization_id' => $this->organization->id,
    ]);
    $otherProduct->categories()->attach($otherCategory, [
        'organization_id' => $this->otherOrganization->id,
    ]);

    expect($product->categories)->toHaveCount(1)
        ->and($product->categories->first()->is($category))->toBeTrue();

    $product->load('categories');
    expect($product->categories)->toHaveCount(1)
        ->and($product->categories->first()->is($category))->toBeTrue();

    $queriedProduct = Product::query()
        ->whereHas('categories', fn ($query) => $query->whereKey($category->id))
        ->withCount('categories')
        ->with('categories')
        ->findOrFail($product->id);

    expect($queriedProduct->categories_count)->toBe(1)
        ->and($queriedProduct->categories->first()->is($category))->toBeTrue();

    expect(Product::query()
        ->whereHas('categories', fn ($query) => $query->whereKey($otherCategory->id))
        ->whereKey($product->id)
        ->exists())->toBeFalse();

    expect(fn (): bool => DB::table('catalog_product_categories')->insert([
        'id'              => (string) str()->uuid(),
        'organization_id' => $this->organization->id,
        'product_id'      => $product->id,
        'category_id'     => $otherCategory->id,
    ]))->toThrow(QueryException::class);
});

it('enforces tenancy matrix for categories', function (): void {
    $category = Category::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'Electronics',
    ]);
    $otherCategory = Category::factory()->create([
        'organization_id' => $this->otherOrganization->id,
        'name'            => 'Other Org Category',
    ]);

    $this->getJson('/v1/catalog/categories')
        ->assertOk()
        ->assertJsonFragment(['id' => $category->id])
        ->assertJsonMissing(['id' => $otherCategory->id]);

    $this->getJson("/v1/catalog/categories/{$category->id}")->assertOk();
    $this->getJson("/v1/catalog/categories/{$otherCategory->id}")->assertForbidden();

    $created = $this->postJson('/v1/catalog/categories?response=resource', [
        'name'      => 'Smartphones',
        'is_active' => true,
    ])->assertCreated();

    $createdId = (string) $created->json('data.id');
    expect(Category::query()->findOrFail($createdId)->organization_id)->toBe($this->organization->id);

    $this->putJson("/v1/catalog/categories/{$category->id}?response=resource", [
        'name'      => 'Gadgets',
        'is_active' => true,
    ])->assertOk();
    $this->putJson("/v1/catalog/categories/{$otherCategory->id}", [
        'name'      => 'Hacked',
        'is_active' => true,
    ])->assertForbidden();

    $this->deleteJson("/v1/catalog/categories/{$createdId}")->assertNoContent();
    $this->getJson("/v1/catalog/categories/{$createdId}")->assertNotFound();
    expect(Category::withTrashed()->whereKey($createdId)->exists())->toBeTrue();
    $this->deleteJson("/v1/catalog/categories/{$otherCategory->id}")->assertForbidden();
});

it('enforces tenancy matrix for products and variants', function (): void {
    $unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
    Unit::factory()->create([
        'group_id'        => $unitGroup->id,
        'ratio'           => 1,
        'organization_id' => null,
    ]);
    app(UnitCache::class)->rewarmUnits();

    $product = Product::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'My Product',
    ]);
    $otherProduct = Product::factory()->create([
        'organization_id' => $this->otherOrganization->id,
        'name'            => 'Other Product',
    ]);

    $catalogItem = CatalogItem::factory()->create([
        'organization_id' => $this->organization->id,
        'unit_group_id'   => $unitGroup->id,
    ]);
    $variant = ProductVariant::factory()->forCatalogItem($catalogItem)->create([
        'product_id' => $product->id,
    ]);
    /** @var CatalogItem $catalogItem */
    $catalogItem = $variant->catalogItem()->firstOrFail();
    app(InventoryInterface::class)->createItem($catalogItem);
    $productCategory = Category::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $product->categories()->attach($productCategory, [
        'organization_id' => $this->organization->id,
    ]);
    $otherCatalogItem = CatalogItem::factory()->create([
        'organization_id' => $this->organization->id,
        'unit_group_id'   => $unitGroup->id,
    ]);
    ProductVariant::factory()->forCatalogItem($otherCatalogItem)->create([
        'product_id' => $product->id,
    ]);
    $otherCatalogItem = CatalogItem::factory()->create([
        'organization_id' => $this->otherOrganization->id,
        'unit_group_id'   => $unitGroup->id,
    ]);
    $otherVariant = ProductVariant::factory()->forCatalogItem($otherCatalogItem)->create([
        'product_id' => $otherProduct->id,
    ]);

    $this->getJson('/v1/catalog/products')
        ->assertOk()
        ->assertJsonFragment(['id' => $product->id])
        ->assertJsonMissing(['id' => $otherProduct->id]);

    $this->getJson("/v1/catalog/products/{$product->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.categories')
        ->assertJsonMissingPath('data.options')
        ->assertJsonMissingPath('data.variants');
    $this->getJson("/v1/catalog/products/{$product->id}?include=categories")
        ->assertOk()
        ->assertJsonPath('data.categories.0.id', $productCategory->id)
        ->assertJsonMissingPath('data.options')
        ->assertJsonMissingPath('data.variants');
    $this->getJson("/v1/catalog/products/{$product->id}?include=variants")
        ->assertOk()
        ->assertJsonPath('data.variants.0.id', $variant->id)
        ->assertJsonMissingPath('data.categories')
        ->assertJsonMissingPath('data.options');
    $this->getJson("/v1/catalog/products/{$otherProduct->id}")->assertForbidden();

    $createdProduct = $this->postJson('/v1/catalog/products?response=resource', [
        'name'      => 'Created Product',
        'is_active' => true,
        'variants'  => [[
            'sku'           => 'CREATED-001',
            'unit_group_id' => $unitGroup->id,
            'is_active'     => true,
            'options'       => [['name' => 'color', 'value' => 'white']],
        ]],
    ])->assertCreated();

    $createdProductId = (string) $createdProduct->json('data.id');
    expect(Product::query()->findOrFail($createdProductId)->organization_id)->toBe($this->organization->id);
    $this->getJson("/v1/catalog/products/{$createdProductId}?include=options")
        ->assertOk()
        ->assertJsonPath('data.options.0.name', 'color')
        ->assertJsonMissingPath('data.categories')
        ->assertJsonMissingPath('data.variants');

    $this->putJson("/v1/catalog/products/{$product->id}?response=resource", [
        'name'      => 'Updated Product',
        'is_active' => true,
    ])->assertOk();
    $this->putJson("/v1/catalog/products/{$otherProduct->id}", [
        'name'      => 'Hacked',
        'is_active' => true,
    ])->assertForbidden();

    $this->getJson("/v1/catalog/products/{$product->id}/variants")->assertOk();
    $this->getJson("/v1/catalog/products/{$otherProduct->id}/variants")->assertForbidden();
    $variant->attachLabels(['status' => ['active']]);

    $variantResponse = $this->getJson("/v1/catalog/products/{$product->id}/variants/{$variant->id}")
        ->assertOk();
    expect($variantResponse->json('data.labels'))->toBeNull();

    $this->getJson("/v1/catalog/products/{$product->id}/variants/{$variant->id}?include=labels")
        ->assertOk()
        ->assertJsonPath('data.labels.0.value', 'active');
    $this->getJson("/v1/catalog/products/{$otherProduct->id}/variants/{$otherVariant->id}")->assertNotFound();

    $createdVariant = $this->postJson("/v1/catalog/products/{$product->id}/variants?response=resource", [
        'variants' => [[
            'sku'           => 'CREATED-VAR-001',
            'unit_group_id' => $unitGroup->id,
            'is_active'     => true,
            'options'       => [['name' => 'size', 'value' => 'm']],
        ]],
    ])->assertCreated();

    $createdVariantId = (string) $createdVariant->json('data.0.id');
    expect(ProductVariant::query()->findOrFail($createdVariantId)->organization_id)->toBe($this->organization->id);

    $this->patchJson("/v1/catalog/products/{$product->id}/variants/{$variant->id}?response=resource", [
        'sku' => 'UPDATED-VAR-SKU',
    ])->assertOk();
    $this->patchJson("/v1/catalog/products/{$otherProduct->id}/variants/{$otherVariant->id}", [
        'sku' => 'HACKED',
    ])->assertNotFound();

    $this->deleteJson("/v1/catalog/products/{$product->id}/variants/{$createdVariantId}")->assertNoContent();
    $this->getJson("/v1/catalog/products/{$product->id}/variants/{$createdVariantId}")->assertNotFound();
    expect(ProductVariant::withTrashed()->whereKey($createdVariantId)->exists())->toBeTrue();
    $this->deleteJson("/v1/catalog/products/{$otherProduct->id}/variants/{$otherVariant->id}")->assertNotFound();
    $this->deleteJson("/v1/catalog/products/{$createdProductId}")->assertNoContent();
    $this->getJson("/v1/catalog/products/{$createdProductId}")->assertNotFound();
    expect(Product::withTrashed()->whereKey($createdProductId)->exists())->toBeTrue();
    $this->deleteJson("/v1/catalog/products/{$otherProduct->id}")->assertForbidden();
});

it('enforces tenancy matrix for options and option values', function (): void {
    $option = Option::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'Color',
    ]);
    $otherOption = Option::factory()->create([
        'organization_id' => $this->otherOrganization->id,
        'name'            => 'Other Color',
    ]);
    $value = OptionValue::factory()->create([
        'organization_id' => $this->organization->id,
        'option_id'       => $option->id,
        'value'           => 'Blue',
    ]);
    $otherValue = OptionValue::factory()->create([
        'organization_id' => $this->otherOrganization->id,
        'option_id'       => $otherOption->id,
        'value'           => 'Red',
    ]);

    $this->getJson('/v1/catalog/options')
        ->assertOk()
        ->assertJsonFragment(['id' => $option->id])
        ->assertJsonMissing(['id' => $otherOption->id]);

    $this->getJson("/v1/catalog/options/{$option->id}")->assertOk();

    $createdOption = $this->postJson('/v1/catalog/options?response=resource', [
        'name'   => 'Size',
        'values' => ['Large'],
    ])->assertCreated();
    $createdOptionId = (string) $createdOption->json('data.id');
    expect(Option::query()->findOrFail($createdOptionId)->organization_id)->toBe($this->organization->id);

    $this->putJson("/v1/catalog/options/{$createdOptionId}?response=resource", [
        'name'   => 'Material',
        'values' => ['Cotton'],
    ])->assertOk();
    $this->deleteJson("/v1/catalog/options/{$createdOptionId}")->assertNoContent();
    $this->getJson("/v1/catalog/options/{$createdOptionId}")->assertNotFound();
    expect(Option::withTrashed()->whereKey($createdOptionId)->exists())->toBeTrue();

    $this->getJson("/v1/catalog/options/{$option->id}/values")->assertOk();
    $this->getJson("/v1/catalog/options/{$otherOption->id}/values")->assertForbidden();
    $this->getJson("/v1/catalog/options/{$option->id}/values/{$value->id}")->assertOk();
    $this->getJson("/v1/catalog/options/{$otherOption->id}/values/{$otherValue->id}")->assertNotFound();

    $createdValue = $this->postJson("/v1/catalog/options/{$option->id}/values?response=resource", [
        'values' => ['Yellow'],
    ])->assertCreated();
    $createdValueId = (string) $createdValue->json('data.0.id');
    expect(OptionValue::query()->findOrFail($createdValueId)->organization_id)->toBe($this->organization->id);

    $this->putJson("/v1/catalog/options/{$option->id}/values/{$value->id}?response=resource", [
        'value' => 'Cyan',
    ])->assertOk();
    $this->putJson("/v1/catalog/options/{$otherOption->id}/values/{$otherValue->id}", [
        'value' => 'Hacked',
    ])->assertNotFound();

    $this->deleteJson("/v1/catalog/options/{$option->id}/values/{$createdValueId}")->assertNoContent();
    $this->getJson("/v1/catalog/options/{$option->id}/values/{$createdValueId}")->assertNotFound();
    expect(OptionValue::withTrashed()->whereKey($createdValueId)->exists())->toBeTrue();
    $this->deleteJson("/v1/catalog/options/{$otherOption->id}/values/{$otherValue->id}")->assertNotFound();
});

it('rejects nested catalog bindings when child does not belong to parent', function (): void {
    $unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
    Unit::factory()->create([
        'group_id'        => $unitGroup->id,
        'ratio'           => 1,
        'organization_id' => null,
    ]);
    app(UnitCache::class)->rewarmUnits();

    $productA = Product::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'Product A',
    ]);
    $productB = Product::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'Product B',
    ]);

    $catalogItemOfB = CatalogItem::factory()->create([
        'organization_id' => $this->organization->id,
        'unit_group_id'   => $unitGroup->id,
    ]);
    $variantOfB = ProductVariant::factory()->forCatalogItem($catalogItemOfB)->create([
        'product_id' => $productB->id,
    ]);

    $optionA = Option::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'Color',
    ]);
    $optionB = Option::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'Size',
    ]);

    $valueOfB = OptionValue::factory()->create([
        'organization_id' => $this->organization->id,
        'option_id'       => $optionB->id,
        'value'           => 'XL',
    ]);

    $this->getJson("/v1/catalog/products/{$productA->id}/variants/{$variantOfB->id}")
        ->assertNotFound();
    $this->patchJson("/v1/catalog/products/{$productA->id}/variants/{$variantOfB->id}", [
        'sku' => 'SHOULD-NOT-PASS',
    ])->assertNotFound();
    $this->deleteJson("/v1/catalog/products/{$productA->id}/variants/{$variantOfB->id}")
        ->assertNotFound();

    $this->getJson("/v1/catalog/options/{$optionA->id}/values/{$valueOfB->id}")
        ->assertNotFound();
    $this->putJson("/v1/catalog/options/{$optionA->id}/values/{$valueOfB->id}", [
        'value' => 'SHOULD-NOT-PASS',
    ])->assertNotFound();
    $this->deleteJson("/v1/catalog/options/{$optionA->id}/values/{$valueOfB->id}")
        ->assertNotFound();
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Http\Resources\BundleResource;
use Lahatre\Catalog\Http\Resources\CategoryResource;
use Lahatre\Catalog\Http\Resources\OptionResource;
use Lahatre\Catalog\Http\Resources\OptionValueResource;
use Lahatre\Catalog\Http\Resources\ProductResource;
use Lahatre\Catalog\Http\Resources\ProductVariantResource;
use Lahatre\Catalog\Http\Resources\ServiceResource;
use Lahatre\Catalog\Http\Resources\StockLocationResource;
use Lahatre\Catalog\Http\Resources\StockTransferResource;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Models\ServiceDeliverableTemplate;
use Lahatre\Catalog\Models\StockTransferLine;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Library\Models\FileAttachment;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;
use Lahatre\Organization\Models\Organization;
use Lahatre\ResponseSelection\Testing\AssertsBoundedQueries;
use Lahatre\ResponseSelection\Testing\AssertsResponseFieldIsolation;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class, AssertsBoundedQueries::class, AssertsResponseFieldIsolation::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'Catalog Selection Organization']);
    setPermissionsTeamId($this->organization->id);

    $user = User::factory()->create();
    $member = OrganizationMember::create(['user_id' => $user->id, 'organization_id' => $this->organization->id]);
    $role = Role::query()->firstOrCreate(['name' => 'catalog-selection-reader', 'guard_name' => 'sanctum']);
    $memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'role_id'         => $role->id,
    ]);

    $permissions = [
        'catalog_category.list', 'catalog_category.retrieve', 'catalog_option.list', 'catalog_option.retrieve', 'catalog_product.list',
        'catalog_product.retrieve', 'catalog_bundle.list', 'catalog_bundle.create', 'catalog_service.list',
        'catalog_stock_location.list', 'catalog_stock_location.create', 'catalog_stock_transfer.list',
    ];
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $memberRole->givePermissionTo($permissions);

    $token = $user->createToken('catalog-selection-token');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);

    $this->unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
    $this->unit = Unit::factory()->create(['organization_id' => null, 'group_id' => $this->unitGroup->id, 'ratio' => 1]);
});

/**
 * A variant with a label, a unit group and an inventory item, so every include it offers has a row.
 */
function catalogSelectionVariant(Product $product, UnitGroup $unitGroup): ProductVariant
{
    $variant = createCatalogProductVariant(
        ['organization_id' => $product->organization_id, 'product_id' => $product->id],
        ['organization_id' => $product->organization_id, 'unit_group_id' => $unitGroup->id],
    );
    $variant->attachLabels(['status' => ['active']]);
    app(InventoryInterface::class)->createItem(CatalogItem::query()->findOrFail($variant->id));

    return $variant;
}

/**
 * A product with a variant, a category, a main image and a gallery image.
 */
function catalogSelectionProduct(UnitGroup $unitGroup): Product
{
    $product = Product::factory()->create(['organization_id' => currentOrganizationId()]);
    catalogSelectionVariant($product, $unitGroup);
    $product->categories()->attach(Category::factory()->create(['organization_id' => currentOrganizationId()]), [
        'organization_id' => currentOrganizationId(),
    ]);
    FileAttachment::factory()->forParent($product)->create(['slot' => 'main', 'position' => 1]);
    FileAttachment::factory()->forParent($product)->create(['slot' => 'gallery', 'position' => 1]);

    return $product;
}

/**
 * A bundle of two variants, created through the API so its catalog item and inventory item exist.
 */
function catalogSelectionBundle(UnitGroup $unitGroup, Unit $unit): void
{
    $product = Product::factory()->create(['organization_id' => currentOrganizationId()]);
    $variants = [catalogSelectionVariant($product, $unitGroup), catalogSelectionVariant($product, $unitGroup)];

    currentTestCase()->postJson('/v1/catalog/bundles', [
        'name'      => 'Selection Pack '.$product->id,
        'is_active' => true,
        'items'     => array_map(fn (ProductVariant $variant): array => [
            'item_type' => CatalogItemType::ProductVariant->value,
            'item_id'   => $variant->id,
            'quantity'  => 1,
            'unit_code' => $unit->code,
        ], $variants),
    ])->assertNoContent();
}

/**
 * A service with a deliverable template, a main image and a gallery image.
 */
function catalogSelectionService(UnitGroup $unitGroup): void
{
    $catalogItem = CatalogItem::factory()->service()->create([
        'organization_id' => currentOrganizationId(),
        'unit_group_id'   => $unitGroup->id,
    ]);
    $service = Service::factory()->forCatalogItem($catalogItem)->create();
    ServiceDeliverableTemplate::factory()->create(['organization_id' => currentOrganizationId(), 'service_id' => $service->id]);
    FileAttachment::factory()->forParent($service)->create(['slot' => 'main', 'position' => 1]);
    FileAttachment::factory()->forParent($service)->create(['slot' => 'gallery', 'position' => 1]);
}

it('renders every field of the catalog lists alone as in the full selection', function (): void {
    $parent = Category::factory()->create(['organization_id' => $this->organization->id]);
    Category::factory()->create(['organization_id' => $this->organization->id, 'parent_id' => $parent->id]);
    $option = Option::factory()->create(['organization_id' => $this->organization->id]);
    OptionValue::factory()->create(['organization_id' => $this->organization->id, 'option_id' => $option->id]);
    $product = catalogSelectionProduct($this->unitGroup);
    catalogSelectionBundle($this->unitGroup, $this->unit);
    catalogSelectionService($this->unitGroup);
    $this->postJson('/v1/catalog/stock-locations', [
        'name'      => 'Selection Warehouse',
        'is_active' => true,
        'address'   => ['line' => '1 Main Street', 'city' => 'Cotonou', 'country' => 'Benin'],
    ])->assertNoContent();
    StockTransferLine::factory()->create();
    // the line factory creates its own unit, which the unit cache does not know yet
    app(UnitCache::class)->rewarmUnits();

    $this->assertFieldsRenderInIsolation('/v1/catalog/categories', CategoryResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/catalog/options', OptionResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/catalog/options/{$option->id}/values", OptionValueResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/catalog/products', ProductResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/catalog/products/{$product->id}/variants", ProductVariantResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/catalog/bundles', BundleResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/catalog/services', ServiceResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/catalog/stock-locations', StockLocationResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/catalog/stock-transfers', StockTransferResource::class, 1);
});

it('runs as many queries for more catalog rows with their includes', function (string $uri, Closure $seed): void {
    $this->assertBoundedQueries(
        fn (int $count) => collect(range(1, $count))->each(fn () => $seed($this->unitGroup, $this->unit)),
        fn () => $this->getJson($uri)->assertOk(),
    );
})->with([
    'products' => [
        '/v1/catalog/products?fields=name,options&include=variants.labels,variants.unit_group,variants.inventory,categories,main_files,gallery_files',
        fn (UnitGroup $unitGroup): Product => catalogSelectionProduct($unitGroup),
    ],
    'bundles' => [
        '/v1/catalog/bundles?include=items.component,unit_group.units,inventory',
        fn (UnitGroup $unitGroup, Unit $unit) => catalogSelectionBundle($unitGroup, $unit),
    ],
    'services' => [
        '/v1/catalog/services?include=deliverable_templates,main_files,gallery_files,unit_group',
        fn (UnitGroup $unitGroup) => catalogSelectionService($unitGroup),
    ],
    'stock transfers' => [
        '/v1/catalog/stock-transfers?include=lines.item,source_location,destination_location',
        fn (): StockTransferLine => tap(StockTransferLine::factory()->create(), fn () => app(UnitCache::class)->rewarmUnits()),
    ],
]);

it('renders the product options and the category tree only when the client lists them', function (): void {
    $parent = Category::factory()->create(['organization_id' => $this->organization->id]);
    $child = Category::factory()->create(['organization_id' => $this->organization->id, 'parent_id' => $parent->id]);

    $this->getJson("/v1/catalog/categories/{$child->id}")->assertOk()->assertJsonMissingPath('data.bloodline');
    $this->getJson("/v1/catalog/categories/{$child->id}?fields=name,bloodline")
        ->assertOk()
        ->assertJsonPath('data.bloodline.0.id', $parent->id)
        ->assertJsonPath('data.bloodline.0.children.0.id', $child->id);

    $product = catalogSelectionProduct($this->unitGroup);

    $this->getJson("/v1/catalog/products/{$product->id}")->assertOk()->assertJsonMissingPath('data.options');
    $this->getJson("/v1/catalog/products/{$product->id}?fields=options")->assertOk()->assertJsonPath('data.options', []);
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Validator;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Http\Requests\BundleStockOperationCreateRequest;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'Bundle API Organization']);
    setPermissionsTeamId($this->organization->id);

    $this->user = User::factory()->create();
    $member = OrganizationMember::create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);
    $role = Role::query()->firstOrCreate([
        'name'       => 'bundle-api-admin',
        'guard_name' => 'sanctum',
    ]);
    $this->memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'role_id'         => $role->id,
    ]);

    $permissions = [
        'catalog_bundle.list',
        'catalog_bundle.retrieve',
        'catalog_bundle.create',
        'catalog_bundle.update',
        'catalog_bundle.delete',
        'catalog_bundle.assemble',
        'catalog_bundle.manage_composition',
    ];
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $this->memberRole->givePermissionTo($permissions);

    $token = $this->user->createToken('bundle-api-token');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $this->memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);

    $this->unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
    $this->unit = Unit::factory()->create([
        'organization_id' => null,
        'group_id'        => $this->unitGroup->id,
        'ratio'           => 1,
    ]);
    $this->displayUnit = Unit::factory()->create([
        'organization_id' => null,
        'group_id'        => $this->unitGroup->id,
        'ratio'           => 10,
    ]);
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $this->variants = collect(range(1, 3))->map(fn (int $index): ProductVariant => createCatalogProductVariant([
        'organization_id' => $this->organization->id,
        'product_id'      => $product->id,
    ], [
        'organization_id' => $this->organization->id,
        'unit_group_id'   => $this->unitGroup->id,
        'sku'             => "HTTP-BUNDLE-{$index}",
    ]));
});

it('exposes the complete bundle CRUD and nested item mutations', function (): void {
    $created = $this->postJson('/v1/catalog/bundles?response=resource&include=items', [
        'name'      => 'HTTP Starter Pack',
        'is_active' => true,
        'items'     => [
            httpBundleItem($this->variants[0], $this->displayUnit, 2),
            httpBundleItem($this->variants[1], $this->unit, 2),
        ],
    ])->assertCreated()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonFragment(['unit_code' => $this->displayUnit->code]);

    $bundleId = (string) $created->json('data.id');
    expect(CatalogItem::query()->findOrFail($bundleId)->is_stockable)->toBeTrue();
    $createdItem = BundleItem::query()->where('bundle_id', $bundleId)->where('item_id', $this->variants[0]->id)->firstOrFail();
    expect($createdItem->quantity)->toBe(20)
        ->and($createdItem->display_unit_code)->toBe($this->displayUnit->code);

    $this->getJson('/v1/catalog/bundles')->assertOk()->assertJsonFragment(['id' => $bundleId]);
    $retrieved = $this->getJson("/v1/catalog/bundles/{$bundleId}?include=items")
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonFragment([
            'id'         => $this->variants[0]->id,
            'product_id' => $this->variants[0]->product_id,
        ])
        ->assertJsonStructure(['data' => ['items' => [['component' => ['name', 'options']]]]]);
    expect(collect($retrieved->json('data.items'))->pluck('item_id')->all())
        ->toEqualCanonicalizing([$this->variants[0]->id, $this->variants[1]->id]);

    $added = $this->postJson("/v1/catalog/bundles/{$bundleId}/items?response=resource", [
        'items' => [httpBundleItem($this->variants[2], $this->unit, 3)],
    ])->assertCreated();
    $bundleItemId = (string) $added->json('data.0.id');

    $this->patchJson("/v1/catalog/bundles/{$bundleId}/items/{$bundleItemId}?response=resource", [
        'quantity'  => 7,
        'unit_code' => $this->displayUnit->code,
    ])->assertOk()
        ->assertJsonPath('data.quantity', 7)
        ->assertJsonPath('data.unit_code', $this->displayUnit->code);

    expect(BundleItem::query()->findOrFail($bundleItemId)->quantity)->toBe(70);

    $this->deleteJson("/v1/catalog/bundles/{$bundleId}/items", ['ids' => [$bundleItemId]])
        ->assertNoContent();

    $remainingId = BundleItem::query()
        ->where('organization_id', $this->organization->id)
        ->where('bundle_id', $bundleId)
        ->firstOrFail()
        ->id;
    $this->deleteJson("/v1/catalog/bundles/{$bundleId}/items", ['ids' => [$remainingId]])
        ->assertUnprocessable();

    $this->patchJson("/v1/catalog/bundles/{$bundleId}?response=resource", [
        'name'      => 'HTTP Renamed Pack',
        'sku'       => 'HTTP-PACK',
        'is_active' => false,
    ])->assertOk()
        ->assertJsonPath('data.name', 'HTTP Renamed Pack')
        ->assertJsonPath('data.sku', 'HTTP-PACK')
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson("/v1/catalog/bundles/{$bundleId}")->assertNoContent();
    $this->getJson("/v1/catalog/bundles/{$bundleId}")->assertNotFound();
});

it('renders bundle item components with their product variant relations', function (): void {
    $created = $this->postJson('/v1/catalog/bundles?response=resource&include=items', [
        'name'  => 'HTTP Component Pack',
        'items' => [
            httpBundleItem($this->variants[0], $this->displayUnit, 2),
            httpBundleItem($this->variants[1], $this->unit, 2),
        ],
    ])->assertCreated();

    $bundleId = (string) $created->json('data.id');

    $response = $this->getJson("/v1/catalog/bundles/{$bundleId}?include=items")
        ->assertOk()
        ->assertJsonStructure(['data' => ['items' => [['component' => ['name', 'options']]]]]);

    expect(collect($response->json('data.items'))->pluck('component.id')->all())
        ->toEqualCanonicalizing($this->variants->take(2)->pluck('id')->all());

    $this->postJson("/v1/catalog/bundles/{$bundleId}/items?response=resource&include=component", [
        'items' => [httpBundleItem($this->variants[2], $this->unit, 1)],
    ])->assertCreated()
        ->assertJsonPath('data.0.component.id', $this->variants[2]->id)
        ->assertJsonStructure(['data' => [['component' => ['name', 'options']]]]);
});

it('rejects inactive catalog items when creating a bundle', function (): void {
    $this->variants[0]->catalogItem()->update(['is_active' => false]);

    $this->postJson('/v1/catalog/bundles', [
        'name'  => 'Inactive Component Pack',
        'items' => [
            httpBundleItem($this->variants[0], $this->unit, 1),
            httpBundleItem($this->variants[1], $this->unit, 1),
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('items.0.item_id');
});

it('persists bundle inventory configuration on create and update', function (): void {
    $created = $this->postJson('/v1/catalog/bundles?response=resource', [
        'name'      => 'Inventory Configuration Pack',
        'is_active' => true,
        'inventory' => [
            'stock_tracking_enabled' => false,
            'is_expirable'           => true,
            'deduction_strategy'     => 'fefo',
        ],
        'items' => [
            httpBundleItem($this->variants[0], $this->unit, 1),
            httpBundleItem($this->variants[1], $this->unit, 1),
        ],
    ])->assertCreated();

    $bundleId = (string) $created->json('data.id');
    $inventoryItem = InventoryItem::query()->where('itemable_id', $bundleId)->firstOrFail();

    expect($inventoryItem->stock_tracking_enabled)->toBeFalse()
        ->and($inventoryItem->is_expirable)->toBeTrue()
        ->and($inventoryItem->deduction_strategy->value)->toBe('fefo');

    $this->patchJson("/v1/catalog/bundles/{$bundleId}?response=resource", [
        'inventory' => [
            'stock_tracking_enabled' => true,
            'is_expirable'           => false,
            'deduction_strategy'     => 'fifo',
        ],
    ])->assertOk();

    $inventoryItem->refresh();

    expect($inventoryItem->stock_tracking_enabled)->toBeTrue()
        ->and($inventoryItem->is_expirable)->toBeFalse()
        ->and($inventoryItem->deduction_strategy->value)->toBe('fifo');
});

it('creates and exposes bundle stock operation history through nested endpoints', function (): void {
    $created = $this->postJson('/v1/catalog/bundles?response=resource&include=items', [
        'name'      => 'HTTP Stock Operation Pack',
        'is_active' => true,
        'items'     => [
            httpBundleItem($this->variants[0], $this->unit, 1),
            httpBundleItem($this->variants[1], $this->unit, 1),
        ],
    ])->assertCreated();

    $bundleId = (string) $created->json('data.id');
    $bundleItemIds = BundleItem::query()
        ->where('bundle_id', $bundleId)
        ->orderBy('created_at')
        ->pluck('id');

    foreach ($this->variants->take(2) as $variant) {
        app(InventoryInterface::class)->createItem(
            CatalogItem::query()->findOrFail($variant->id),
        );
    }

    $stockLocation = StockLocation::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    app(InventoryInterface::class)->createLocation($stockLocation);

    $operation = $this->postJson("/v1/catalog/bundles/{$bundleId}/stock-operations?response=resource", [
        'type'        => 'attach',
        'quantity'    => 2,
        'location_id' => $stockLocation->id,
        'components'  => $bundleItemIds->map(fn (string $id): array => [
            'bundle_item_id' => $id,
        ])->all(),
    ])->assertCreated()
        ->assertJsonPath('data.type', 'attach')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.quantity', 2);

    $operationId = (string) $operation->json('data.id');

    $this->getJson("/v1/catalog/bundles/{$bundleId}/stock-operations")
        ->assertOk()
        ->assertJsonFragment(['id' => $operationId])
        ->assertJsonFragment(['status' => 'draft']);

    $this->getJson("/v1/catalog/bundles/{$bundleId}/stock-operations/{$operationId}")
        ->assertOk()
        ->assertJsonPath('data.id', $operationId)
        ->assertJsonPath('data.bundle_id', $bundleId);

    $this->memberRole->revokePermissionTo('catalog_bundle.assemble');

    $this->getJson("/v1/catalog/bundles/{$bundleId}/stock-operations")
        ->assertForbidden();
});

it('validates stock operation locations and type-specific stock fields', function (): void {
    $location = StockLocation::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $request = new BundleStockOperationCreateRequest;
    $componentId = (string) str()->uuid7();
    $stockId = (string) str()->uuid7();

    $attach = [
        'type'        => 'attach',
        'quantity'    => 1,
        'location_id' => $location->id,
        'components'  => [['bundle_item_id' => $componentId]],
    ];

    expect(Validator::make($attach, $request->rules())->passes())->toBeTrue()
        ->and(Validator::make([...$attach, 'location_id' => (string) str()->uuid7()], $request->rules())->fails())->toBeTrue()
        ->and(Validator::make([...$attach, 'stock_ids' => [$stockId]], $request->rules())->fails())->toBeTrue()
        ->and(Validator::make([
            ...$attach,
            'components' => [['bundle_item_id' => $componentId, 'expiration_date' => '2026-01-01']],
        ], $request->rules())->fails())->toBeTrue();

    $detach = [
        'type'        => 'detach',
        'quantity'    => 1,
        'location_id' => $location->id,
        'components'  => [['bundle_item_id' => $componentId]],
    ];

    expect(Validator::make($detach, $request->rules())->passes())->toBeTrue()
        ->and(Validator::make([...$detach, 'expiration_date' => '2026-01-01'], $request->rules())->fails())->toBeTrue()
        ->and(Validator::make([
            ...$detach,
            'components' => [['bundle_item_id' => $componentId, 'stock_ids' => [$stockId]]],
        ], $request->rules())->fails())->toBeTrue();
});

it('scopes bundle item bindings to their parent bundle', function (): void {
    $firstBundle = $this->postJson('/v1/catalog/bundles?response=resource', [
        'name'  => 'First Scoped Bundle',
        'items' => [
            httpBundleItem($this->variants[0], $this->unit, 1),
            httpBundleItem($this->variants[1], $this->unit, 1),
        ],
    ])->assertCreated();

    $secondBundle = $this->postJson('/v1/catalog/bundles?response=resource', [
        'name'  => 'Second Scoped Bundle',
        'items' => [
            httpBundleItem($this->variants[0], $this->unit, 1),
            httpBundleItem($this->variants[1], $this->unit, 1),
        ],
    ])->assertCreated();

    $firstBundleId = (string) $firstBundle->json('data.id');
    $secondBundleId = (string) $secondBundle->json('data.id');
    $secondBundleItemId = BundleItem::query()
        ->where('bundle_id', $secondBundleId)
        ->firstOrFail()
        ->id;

    $this->patchJson("/v1/catalog/bundles/{$firstBundleId}/items/{$secondBundleItemId}", [
        'quantity'  => 2,
        'unit_code' => $this->unit->code,
    ])->assertNotFound();
});

/** @return array{item_type: string, item_id: string, quantity: int, unit_code: string} */
function httpBundleItem(ProductVariant $variant, Unit $unit, int $quantity): array
{
    return [
        'item_type' => CatalogItemType::ProductVariant->value,
        'item_id'   => $variant->id,
        'quantity'  => $quantity,
        'unit_code' => $unit->code,
    ];
}

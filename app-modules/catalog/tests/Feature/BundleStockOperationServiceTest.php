<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection as SupportCollection;
use Lahatre\Catalog\Data\BundleData;
use Lahatre\Catalog\Data\BundleItemData;
use Lahatre\Catalog\Data\BundleItemQuantityData;
use Lahatre\Catalog\Data\BundleStockOperationData;
use Lahatre\Catalog\Enums\BundleStockOperationStatus;
use Lahatre\Catalog\Enums\BundleStockOperationType;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Exceptions\BundleException;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\BundleStockOperation;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Services\BundleService;
use Lahatre\Catalog\Services\BundleStockOperationService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->bundleService = app(BundleService::class);
    $this->operationService = app(BundleStockOperationService::class);
    $this->unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
    $this->unit = Unit::factory()->create([
        'organization_id' => null,
        'group_id'        => $this->unitGroup->id,
        'ratio'           => 1,
    ]);
    $this->currency = Currency::query()->firstOrCreate(
        ['code' => 'XOF'],
        [
            'name'      => 'West African CFA franc',
            'symbol'    => 'F',
            'precision' => 0,
        ],
    );
    $this->stockLocation = StockLocation::factory()->create([
        'organization_id' => $this->organizationId,
    ]);
    app(InventoryInterface::class)->createLocation($this->stockLocation);
    $this->location = $this->stockLocation->inventoryLocation()->firstOrFail();
    $product = Product::factory()->create(['organization_id' => $this->organizationId]);
    $this->variants = collect(range(1, 3))->map(
        fn (int $index): ProductVariant => createCatalogProductVariant([
            'organization_id' => $this->organizationId,
            'product_id'      => $product->id,
        ], [
            'organization_id' => $this->organizationId,
            'unit_group_id'   => $this->unitGroup->id,
            'sku'             => "STOCK-BUNDLE-{$index}",
        ])
    );

    foreach ($this->variants as $variant) {
        app(InventoryInterface::class)->createItem(
            CatalogItem::query()->findOrFail($variant->id),
        );
    }
});

it('prevents operations from referencing a location in another organization', function (): void {
    $bundle = createBundle($this->bundleService, $this->variants, $this->unit);
    $otherOrganization = Organization::factory()->create();
    $foreignLocation = StockLocation::factory()->create([
        'organization_id' => $otherOrganization->id,
    ]);

    expect(fn (): BundleStockOperation => BundleStockOperation::factory()->create([
        'organization_id' => $this->organizationId,
        'bundle_id'       => $bundle->id,
        'location_id'     => $foreignLocation->id,
    ]))->toThrow(QueryException::class);
});

it('prevents operations from using an inactive stock location', function (): void {
    $bundle = createBundle($this->bundleService, $this->variants, $this->unit);
    $this->location->update(['is_active' => false]);

    expect(fn (): BundleStockOperation => $this->operationService->create(
        $bundle,
        BundleStockOperationData::fromArray([
            'type'        => BundleStockOperationType::Attach->value,
            'quantity'    => 1,
            'location_id' => $this->stockLocation->id,
            'components'  => $bundle->items->map(fn (BundleItem $item): array => [
                'bundle_item_id' => $item->id,
            ])->all(),
        ]),
    ))->toThrow(BundleException::class);
});

it('attaches component stock into bundle stock and records the operation', function (): void {
    $bundle = createBundle($this->bundleService, $this->variants, $this->unit);
    /** @var Collection<int, BundleItem> $bundleItems */
    $bundleItems = $bundle->items()->orderBy('created_at')->get();

    createStock($this->variants[0], 8, 5_000, $this->organizationId, $this->location, $this->currency, $this->unit);
    createStock($this->variants[1], 10, 5_000, $this->organizationId, $this->location, $this->currency, $this->unit);
    createStock($this->variants[2], 2, 5_000, $this->organizationId, $this->location, $this->currency, $this->unit);

    $operation = $this->operationService->create(
        $bundle,
        BundleStockOperationData::fromArray([
            'type'        => BundleStockOperationType::Attach->value,
            'quantity'    => 2,
            'location_id' => $this->stockLocation->id,
            'components'  => $bundleItems->map(fn (BundleItem $item): array => [
                'bundle_item_id' => $item->id,
            ])->all(),
        ]),
    );

    expect($operation->status)->toBe(BundleStockOperationStatus::Draft);

    $completed = $this->operationService->complete($bundle, $operation->id);
    /** @var Collection<int, InventoryStock> $bundleStocks */
    $bundleStocks = bundleStock($bundle, $this->organizationId, $this->location);

    expect($completed->status)->toBe(BundleStockOperationStatus::Completed)
        ->and($completed->out_transaction_id)->not->toBeNull()
        ->and($completed->in_transaction_id)->not->toBeNull()
        ->and(stockFor($this->variants[0], $this->organizationId, $this->location)->sum('remaining'))->toBe(0)
        ->and(stockFor($this->variants[1], $this->organizationId, $this->location)->sum('remaining'))->toBe(0)
        ->and(stockFor($this->variants[2], $this->organizationId, $this->location)->sum('remaining'))->toBe(0)
        ->and($bundleStocks->sum('remaining'))->toBe(2)
        ->and($bundleStocks->sum(fn (InventoryStock $stock): int => $stock->unit_cost * $stock->remaining))
        ->toBe(100_000);
});

it('detaches bundle stock and allocates its cost by component quantity', function (): void {
    $bundle = createBundle($this->bundleService, $this->variants, $this->unit);
    /** @var Collection<int, BundleItem> $bundleItems */
    $bundleItems = $bundle->items()->orderBy('created_at')->get();

    createStock($this->variants[0], 8, 5_000, $this->organizationId, $this->location, $this->currency, $this->unit);
    createStock($this->variants[1], 10, 5_000, $this->organizationId, $this->location, $this->currency, $this->unit);
    createStock($this->variants[2], 2, 5_000, $this->organizationId, $this->location, $this->currency, $this->unit);

    $attach = $this->operationService->create(
        $bundle,
        BundleStockOperationData::fromArray([
            'type'        => BundleStockOperationType::Attach->value,
            'quantity'    => 2,
            'location_id' => $this->stockLocation->id,
            'components'  => $bundleItems->map(fn (BundleItem $item): array => [
                'bundle_item_id' => $item->id,
            ])->all(),
        ]),
    );
    $this->operationService->complete($bundle, $attach->id);

    $detach = $this->operationService->create(
        $bundle,
        BundleStockOperationData::fromArray([
            'type'        => BundleStockOperationType::Detach->value,
            'quantity'    => 1,
            'location_id' => $this->stockLocation->id,
            'components'  => $bundleItems->map(fn (BundleItem $item): array => [
                'bundle_item_id' => $item->id,
            ])->all(),
        ]),
    );
    $completed = $this->operationService->complete($bundle, $detach->id);

    expect($completed->status)->toBe(BundleStockOperationStatus::Completed)
        ->and(bundleStock($bundle, $this->organizationId, $this->location)->sum('remaining'))->toBe(1)
        ->and(stockFor($this->variants[0], $this->organizationId, $this->location)->sum('remaining'))->toBe(4)
        ->and(stockFor($this->variants[1], $this->organizationId, $this->location)->sum('remaining'))->toBe(5)
        ->and(stockFor($this->variants[2], $this->organizationId, $this->location)->sum('remaining'))->toBe(1)
        ->and(stockFor($this->variants[0], $this->organizationId, $this->location)->last()->getAttribute('unit_cost'))->toBe(5_000)
        ->and(stockFor($this->variants[1], $this->organizationId, $this->location)->last()->getAttribute('unit_cost'))->toBe(5_000)
        ->and(stockFor($this->variants[2], $this->organizationId, $this->location)->last()->getAttribute('unit_cost'))->toBe(5_000);
});

it('does not duplicate stock when completing an operation twice', function (): void {
    $bundle = createBundle($this->bundleService, $this->variants, $this->unit);
    /** @var Collection<int, BundleItem> $bundleItems */
    $bundleItems = $bundle->items()->orderBy('created_at')->get();
    foreach ($this->variants as $index => $variant) {
        createStock($variant, [4, 5, 1][$index], 1_000, $this->organizationId, $this->location, $this->currency, $this->unit);
    }

    $operation = $this->operationService->create(
        $bundle,
        BundleStockOperationData::fromArray([
            'type'        => BundleStockOperationType::Attach->value,
            'quantity'    => 1,
            'location_id' => $this->stockLocation->id,
            'components'  => $bundleItems->map(fn (BundleItem $item): array => [
                'bundle_item_id' => $item->id,
            ])->all(),
        ]),
    );

    $first = $this->operationService->complete($bundle, $operation->id);
    $second = $this->operationService->complete($bundle, $operation->id);

    expect($second->id)->toBe($first->id)
        ->and(bundleStock($bundle, $this->organizationId, $this->location)->sum('remaining'))->toBe(1)
        ->and(bundleStock($bundle, $this->organizationId, $this->location))->toHaveCount(1);
});

it('prevents composition changes while the bundle has active stock', function (): void {
    $bundle = createBundle($this->bundleService, $this->variants, $this->unit);
    /** @var BundleItem $bundleItem */
    $bundleItem = $bundle->items()->firstOrFail();
    /** @var CatalogItem $bundleCatalogItem */
    $bundleCatalogItem = $bundle->catalogItem()->firstOrFail();

    createStockForInventoryItem(
        $bundleCatalogItem->inventoryItem()->firstOrFail()->id,
        1,
        1_000,
        $this->organizationId,
        $this->location,
        $this->currency,
        $this->unit,
    );

    expect(fn () => $this->bundleService->addItems($bundle, collect([
        BundleItemData::fromArray([
            'item_type' => CatalogItemType::ProductVariant->value,
            'item_id'   => $this->variants[2]->id,
            'quantity'  => 1,
            'unit_code' => $this->unit->code,
        ]),
    ])))->toThrow(BundleException::class);

    expect(fn () => $this->bundleService->updateItem(
        $bundle,
        $bundleItem,
        BundleItemQuantityData::fromArray([
            'quantity'  => 2,
            'unit_code' => $this->unit->code,
        ]),
    ))->toThrow(BundleException::class);

    expect(fn () => $this->bundleService->removeItems($bundle, [$bundleItem->id]))
        ->toThrow(BundleException::class);
});

function createBundle(BundleService $service, SupportCollection $variants, Unit $unit): Bundle
{
    return $service->create(BundleData::fromArray([
        'name'      => 'Assembly Bundle',
        'is_active' => true,
        'items'     => [
            [
                'item_type' => CatalogItemType::ProductVariant->value,
                'item_id'   => $variants[0]->id,
                'quantity'  => 4,
                'unit_code' => $unit->code,
            ],
            [
                'item_type' => CatalogItemType::ProductVariant->value,
                'item_id'   => $variants[1]->id,
                'quantity'  => 5,
                'unit_code' => $unit->code,
            ],
            [
                'item_type' => CatalogItemType::ProductVariant->value,
                'item_id'   => $variants[2]->id,
                'quantity'  => 1,
                'unit_code' => $unit->code,
            ],
        ],
    ]));
}

function createStock(
    ProductVariant $variant,
    int $quantity,
    int $unitCost,
    string $organizationId,
    InventoryLocation $location,
    Currency $currency,
    Unit $unit,
): InventoryStock {
    $catalogItem = CatalogItem::query()->findOrFail($variant->id);
    $inventoryItem = $catalogItem->inventoryItem()->firstOrFail();

    return createStockForInventoryItem(
        $inventoryItem->id,
        $quantity,
        $unitCost,
        $organizationId,
        $location,
        $currency,
        $unit,
    );
}

function createStockForInventoryItem(
    string $inventoryItemId,
    int $quantity,
    int $unitCost,
    string $organizationId,
    InventoryLocation $location,
    Currency $currency,
    Unit $unit,
): InventoryStock {
    return InventoryStock::factory()->create([
        'organization_id' => $organizationId,
        'item_id'         => $inventoryItemId,
        'location_id'     => $location->id,
        'unit_cost'       => $unitCost,
        'quantity'        => $quantity,
        'remaining'       => $quantity,
        'currency_code'   => $currency->code,
        'base_unit_code'  => $unit->code,
    ]);
}

function stockFor(
    ProductVariant $variant,
    string $organizationId,
    InventoryLocation $location,
): Collection {
    /** @var CatalogItem $catalogItem */
    $catalogItem = CatalogItem::query()->findOrFail($variant->id);
    $inventoryItem = $catalogItem->inventoryItem()->firstOrFail();

    return InventoryStock::query()
        ->where('organization_id', $organizationId)
        ->where('item_id', $inventoryItem->id)
        ->where('location_id', $location->id)
        ->orderBy('created_at')
        ->get();
}

function bundleStock(
    Bundle $bundle,
    string $organizationId,
    InventoryLocation $location,
): Collection {
    /** @var CatalogItem $catalogItem */
    $catalogItem = $bundle->catalogItem()->firstOrFail();
    $inventoryItem = $catalogItem->inventoryItem()->firstOrFail();

    return InventoryStock::query()
        ->where('organization_id', $organizationId)
        ->where('item_id', $inventoryItem->id)
        ->where('location_id', $location->id)
        ->orderBy('created_at')
        ->get();
}

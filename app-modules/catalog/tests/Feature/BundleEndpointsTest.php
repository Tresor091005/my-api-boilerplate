<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lahatre\Catalog\Data\BundleData;
use Lahatre\Catalog\Data\BundleFilterData;
use Lahatre\Catalog\Data\BundleItemData;
use Lahatre\Catalog\Data\BundleItemQuantityData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Exceptions\BundleException;
use Lahatre\Catalog\Exceptions\CatalogItemException;
use Lahatre\Catalog\Http\Resources\BundleItemResource;
use Lahatre\Catalog\Http\Resources\BundleResource;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\BundleService;
use Lahatre\Catalog\Services\TransactionalCatalogItemService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->service = app(BundleService::class);

    $this->unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
    $this->unit = Unit::factory()->create([
        'organization_id' => null,
        'group_id'        => $this->unitGroup->id,
        'ratio'           => 1,
    ]);
    $this->product = Product::factory()->create(['organization_id' => $this->organizationId]);
    $this->variants = collect(range(1, 4))->map(fn (int $index): ProductVariant => createCatalogProductVariant([
        'organization_id' => $this->organizationId,
        'product_id'      => $this->product->id,
    ], [
        'organization_id' => $this->organizationId,
        'unit_group_id'   => $this->unitGroup->id,
        'sku'             => "BUNDLE-COMPONENT-{$index}",
        'is_active'       => $index !== 4,
    ]));
});

it('manages a bundle and its CatalogItem as one aggregate', function (): void {
    $bundle = $this->service->create(BundleData::fromArray([
        'name'      => 'Starter Pack',
        'is_active' => true,
        'items'     => [
            bundleItemPayload($this->variants[0], $this->unit, 1),
            bundleItemPayload($this->variants[1], $this->unit, 2),
        ],
    ]));

    $catalogItem = CatalogItem::query()->findOrFail($bundle->id);

    expect($catalogItem->item_type)->toBe(CatalogItemType::Bundle)
        ->and($catalogItem->is_stockable)->toBeTrue()
        ->and($catalogItem->is_active)->toBeTrue()
        ->and($catalogItem->sku)->not->toBeEmpty()
        ->and($bundle->handle)->toBe('starter-pack')
        ->and($bundle->items()->count())->toBe(2)
        ->and(InventoryItem::query()->where('itemable_id', $bundle->id)->exists())->toBeTrue();

    $component = $bundle->items()->firstOrFail()->component;
    expect($component)->toBeInstanceOf(ProductVariant::class);

    $pageIds = collect($this->service->paginate(BundleFilterData::fromArray([
        'name' => 'Starter',
    ]))->items())->pluck('id');
    expect($pageIds)->toContain($bundle->id);

    $updated = $this->service->update($bundle, BundleData::fromArray([
        'name'      => 'Renamed Starter Pack',
        'sku'       => 'BUNDLE-STARTER',
        'is_active' => false,
    ], missingFields: ['items']));

    expect($updated->name)->toBe('Renamed Starter Pack')
        ->and($updated->handle)->toBe('starter-pack')
        ->and($updated->catalogItem()->firstOrFail()->sku)->toBe('BUNDLE-STARTER')
        ->and($updated->catalogItem()->firstOrFail()->is_active)->toBeFalse();

    $activeVariant = createCatalogProductVariant([
        'organization_id' => $this->organizationId,
        'product_id'      => $this->product->id,
    ], [
        'organization_id' => $this->organizationId,
        'unit_group_id'   => $this->unitGroup->id,
        'sku'             => 'BUNDLE-COMPONENT-5',
        'is_active'       => true,
    ]);

    $addedItems = $this->service->addItems($updated, collect([
        BundleItemData::fromArray(bundleItemPayload($this->variants[2], $this->unit, 3)),
        BundleItemData::fromArray(bundleItemPayload($activeVariant, $this->unit, 4)),
    ]));

    expect($addedItems)->toHaveCount(2)
        ->and($addedItems->firstOrFail()->item_type)->toBe(CatalogItemType::ProductVariant->value)
        ->and($updated->items()->count())->toBe(4);

    $item = $addedItems->firstOrFail();
    $item = $this->service->updateItem(
        $updated,
        $item,
        BundleItemQuantityData::fromArray([
            'quantity'  => 8,
            'unit_code' => $this->unit->code,
        ]),
    );
    expect($item->quantity)->toBe(8);

    $this->service->removeItems($updated, $addedItems->pluck('id')->all());
    expect($updated->items()->count())->toBe(2);

    $this->service->delete($updated);
    expect(Bundle::query()->whereKey($updated->id)->exists())->toBeFalse()
        ->and(Bundle::withTrashed()->whereKey($updated->id)->exists())->toBeTrue()
        ->and(BundleItem::query()->where('bundle_id', $updated->id)->exists())->toBeFalse()
        ->and(CatalogItem::query()->whereKey($updated->id)->exists())->toBeFalse();
});

it('resolves a bundle item component through its morph type and id', function (): void {
    $bundle = $this->service->create(BundleData::fromArray([
        'name'  => 'Component Resolution Pack',
        'items' => [
            bundleItemPayload($this->variants[0], $this->unit, 1),
            bundleItemPayload($this->variants[1], $this->unit, 1),
        ],
    ]));

    expect($bundle->items()->firstOrFail()->component)->toBeInstanceOf(ProductVariant::class);
});

it('omits catalog item fields when the relation is not loaded', function (): void {
    $bundle = Bundle::factory()->make([
        'organization_id' => $this->organizationId,
    ]);

    $resource = BundleResource::make($bundle)->resolve();

    expect($resource)
        ->not->toHaveKeys(['sku', 'unit_group_id', 'is_active']);
});

it('enforces component uniqueness units and the two-item minimum', function (): void {
    $payload = bundleItemPayload($this->variants[0], $this->unit, 1);

    expect(fn () => $this->service->create(BundleData::fromArray([
        'name'  => 'Single Item Pack',
        'items' => [$payload],
    ])))->toThrow(BundleException::class);

    expect(fn () => $this->service->create(BundleData::fromArray([
        'name'  => 'Invalid Quantity Pack',
        'items' => [
            bundleItemPayload($this->variants[0], $this->unit, 0),
            bundleItemPayload($this->variants[1], $this->unit, 1),
        ],
    ])))->toThrow(BundleException::class);

    expect(fn () => $this->service->create(BundleData::fromArray([
        'name'  => 'Duplicate Pack',
        'items' => [$payload, $payload],
    ])))->toThrow(BundleException::class);

    expect(fn () => $this->service->create(BundleData::fromArray([
        'name'  => 'Inactive Item Pack',
        'items' => [
            bundleItemPayload($this->variants[0], $this->unit, 1),
            bundleItemPayload($this->variants[3], $this->unit, 1),
        ],
    ])))->toThrow(BundleException::class);

    $otherUnitGroup = UnitGroup::factory()->create(['organization_id' => null]);
    $otherUnit = Unit::factory()->create([
        'organization_id' => null,
        'group_id'        => $otherUnitGroup->id,
        'ratio'           => 1,
    ]);

    expect(fn () => $this->service->create(BundleData::fromArray([
        'name'  => 'Invalid Unit Pack',
        'items' => [
            bundleItemPayload($this->variants[0], $otherUnit, 1),
            bundleItemPayload($this->variants[1], $this->unit, 1),
        ],
    ])))->toThrow(BundleException::class);

    $bundle = $this->service->create(BundleData::fromArray([
        'name'  => 'Minimum Pack',
        'items' => [
            bundleItemPayload($this->variants[0], $this->unit, 1),
            bundleItemPayload($this->variants[1], $this->unit, 1),
        ],
    ]));

    expect(fn () => $this->service->removeItems($bundle, [$bundle->items()->firstOrFail()->id]))
        ->toThrow(BundleException::class);
    expect($bundle->items()->count())->toBe(2);

    $missingItemId = (string) Str::uuid7();
    $existingItemId = $bundle->items()->firstOrFail()->id;
    $exception = null;

    try {
        $this->service->removeItems($bundle, [$existingItemId, $missingItemId]);
    } catch (BundleException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(BundleException::class)
        ->and($exception?->context()['item_ids'])->toBe([$missingItemId])
        ->and($bundle->items()->count())->toBe(2);
});

it('converts submitted bundle quantities to base units and back in resources', function (): void {
    $displayUnit = Unit::factory()->create([
        'organization_id' => null,
        'group_id'        => $this->unitGroup->id,
        'ratio'           => 10,
    ]);

    $bundle = $this->service->create(BundleData::fromArray([
        'name'  => 'Converted Pack',
        'items' => [
            bundleItemPayload($this->variants[0], $displayUnit, 2),
            bundleItemPayload($this->variants[1], $this->unit, 1),
        ],
    ]));

    $item = $bundle->items()->where('item_id', $this->variants[0]->id)->firstOrFail();

    expect($item->quantity)->toBe(20)
        ->and($item->display_unit_code)->toBe($displayUnit->code)
        ->and(BundleItemResource::make($item)->resolve()['quantity'])->toBe(2)
        ->and(BundleItemResource::make($item)->resolve()['unit_code'])->toBe($displayUnit->code);
});

it('prevents deleting a CatalogItem used by a bundle', function (): void {
    $bundle = $this->service->create(BundleData::fromArray([
        'name'  => 'Protected Pack',
        'items' => [
            bundleItemPayload($this->variants[0], $this->unit, 1),
            bundleItemPayload($this->variants[1], $this->unit, 1),
        ],
    ]));

    $catalogItem = CatalogItem::query()->findOrFail($this->variants[0]->id);

    expect(fn () => app(TransactionalCatalogItemService::class)->delete($catalogItem))
        ->toThrow(CatalogItemException::class);
    expect(CatalogItem::query()->whereKey($catalogItem->id)->exists())->toBeTrue()
        ->and($bundle->items()->count())->toBe(2);
});

/** @return array{item_type: string, item_id: string, quantity: int, unit_code: string} */
function bundleItemPayload(ProductVariant $variant, Unit $unit, int $quantity): array
{
    return [
        'item_type' => CatalogItemType::ProductVariant->value,
        'item_id'   => $variant->id,
        'quantity'  => $quantity,
        'unit_code' => $unit->code,
    ];
}

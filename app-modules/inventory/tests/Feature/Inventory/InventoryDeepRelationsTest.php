<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Tests\Concerns\InteractsWithInventoryTestFixtures;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithInventoryTestFixtures::class);

beforeEach(function (): void {
    $this->ensureInventoryTestTables();

    $this->currency = Currency::factory()->create();
    currentTestCase()->configureInventoryCurrency($this->currency->code);
    $this->group = UnitGroup::factory()->create(['is_builtin' => false]);
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);
});

it('scopes item deep stocks and movements through every organization boundary', function (): void {
    $material = $this->createTestMaterial();
    $location = InventoryLocation::factory()->create();
    $item = InventoryItem::factory()->create([
        'itemable_type'  => $material->getMorphClass(),
        'itemable_id'    => $material->id,
        'base_unit_code' => $this->unit->code,
    ]);
    $visibleStock = InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create();
    $visibleMovement = InventoryMovement::factory()->create([
        'transaction_id' => InventoryTransaction::factory(),
        'item_id'        => $item->id,
        'stock_id'       => $visibleStock->id,
        'location_id'    => $location->id,
        'unit_code'      => $this->unit->code,
        'currency_code'  => $this->currency->code,
    ]);

    $foreignItem = InventoryItem::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'itemable_type'   => $material->getMorphClass(),
        'itemable_id'     => $material->id,
        'base_unit_code'  => $this->unit->code,
    ]);
    $foreignStockThroughItem = InventoryStock::factory()
        ->for($foreignItem, 'item')
        ->for($location, 'location')
        ->create(['organization_id' => $this->otherOrganizationId]);
    $foreignMovementThroughItem = InventoryMovement::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'transaction_id'  => InventoryTransaction::factory(['organization_id' => $this->otherOrganizationId]),
        'item_id'         => $foreignItem->id,
        'stock_id'        => $foreignStockThroughItem->id,
        'location_id'     => $location->id,
        'unit_code'       => $this->unit->code,
        'currency_code'   => $this->currency->code,
    ]);
    $foreignStockThroughTarget = InventoryStock::factory()
        ->for($item, 'item')
        ->for($location, 'location')
        ->create(['organization_id' => $this->otherOrganizationId]);
    $foreignMovementThroughTarget = InventoryMovement::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'transaction_id'  => InventoryTransaction::factory(['organization_id' => $this->otherOrganizationId]),
        'item_id'         => $item->id,
        'stock_id'        => $foreignStockThroughTarget->id,
        'location_id'     => $location->id,
        'unit_code'       => $this->unit->code,
        'currency_code'   => $this->currency->code,
    ]);

    expect($material->inventoryItemStocks()->pluck('inventory_stocks.id')->all())
        ->toBe([$visibleStock->id])
        ->and($material->inventoryItemMovements()->pluck('inventory_movements.id')->all())
        ->toBe([$visibleMovement->id])
        ->and($foreignMovementThroughItem->exists)->toBeTrue()
        ->and($foreignMovementThroughTarget->exists)->toBeTrue();
});

it('scopes location deep stocks and movements through every organization boundary', function (): void {
    $warehouse = $this->createTestWarehouse();
    $item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $location = InventoryLocation::factory()->create([
        'external_type' => $warehouse->getMorphClass(),
        'external_id'   => $warehouse->id,
    ]);
    $visibleStock = InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create();
    $visibleMovement = InventoryMovement::factory()->create([
        'transaction_id' => InventoryTransaction::factory(),
        'item_id'        => $item->id,
        'stock_id'       => $visibleStock->id,
        'location_id'    => $location->id,
        'unit_code'      => $this->unit->code,
        'currency_code'  => $this->currency->code,
    ]);

    $foreignLocation = InventoryLocation::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'external_type'   => $warehouse->getMorphClass(),
        'external_id'     => $warehouse->id,
    ]);
    $foreignStockThroughLocation = InventoryStock::factory()
        ->for($item, 'item')
        ->for($foreignLocation, 'location')
        ->create(['organization_id' => $this->otherOrganizationId]);
    $foreignMovementThroughLocation = InventoryMovement::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'transaction_id'  => InventoryTransaction::factory(['organization_id' => $this->otherOrganizationId]),
        'item_id'         => $item->id,
        'stock_id'        => $foreignStockThroughLocation->id,
        'location_id'     => $foreignLocation->id,
        'unit_code'       => $this->unit->code,
        'currency_code'   => $this->currency->code,
    ]);
    $foreignStockThroughTarget = InventoryStock::factory()
        ->for($item, 'item')
        ->for($location, 'location')
        ->create(['organization_id' => $this->otherOrganizationId]);
    $foreignMovementThroughTarget = InventoryMovement::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'transaction_id'  => InventoryTransaction::factory(['organization_id' => $this->otherOrganizationId]),
        'item_id'         => $item->id,
        'stock_id'        => $foreignStockThroughTarget->id,
        'location_id'     => $location->id,
        'unit_code'       => $this->unit->code,
        'currency_code'   => $this->currency->code,
    ]);

    expect($warehouse->inventoryLocationStocks()->pluck('inventory_stocks.id')->all())
        ->toBe([$visibleStock->id])
        ->and($warehouse->inventoryLocationMovements()->pluck('inventory_movements.id')->all())
        ->toBe([$visibleMovement->id])
        ->and($foreignMovementThroughLocation->exists)->toBeTrue()
        ->and($foreignMovementThroughTarget->exists)->toBeTrue();
});

it('keeps only active lots in item and location deep stock relations', function (): void {
    $material = $this->createTestMaterial();
    $warehouse = $this->createTestWarehouse();
    $item = InventoryItem::factory()->create([
        'itemable_type'  => $material->getMorphClass(),
        'itemable_id'    => $material->id,
        'base_unit_code' => $this->unit->code,
    ]);
    $location = InventoryLocation::factory()->create([
        'external_type' => $warehouse->getMorphClass(),
        'external_id'   => $warehouse->id,
    ]);
    $activeStock = InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create([
        'remaining' => 5,
    ]);
    InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create([
        'remaining' => 0,
    ]);

    expect($material->activeInventoryItemStocks()->pluck('inventory_stocks.id')->all())
        ->toBe([$activeStock->id])
        ->and($warehouse->activeInventoryLocationStocks()->pluck('inventory_stocks.id')->all())
        ->toBe([$activeStock->id]);
});

it('preserves item and location stock summary aggregation', function (): void {
    $material = $this->createTestMaterial();
    $warehouse = $this->createTestWarehouse();
    $item = InventoryItem::factory()->create([
        'itemable_type'  => $material->getMorphClass(),
        'itemable_id'    => $material->id,
        'base_unit_code' => $this->unit->code,
    ]);
    $location = InventoryLocation::factory()->create([
        'external_type' => $warehouse->getMorphClass(),
        'external_id'   => $warehouse->id,
    ]);
    InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create([
        'remaining' => 5,
    ]);
    InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create([
        'remaining' => 7,
    ]);
    InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create([
        'remaining' => 0,
    ]);

    $itemSummaries = $material->inventoryItemStockSummaries()->get();
    $locationSummaries = $warehouse->inventoryLocationStockSummaries()->get();
    $material->load('inventoryItemStockSummaries');
    $warehouse->load('inventoryLocationStockSummaries');

    expect($itemSummaries)->toHaveCount(1)
        ->and((int) $itemSummaries->firstOrFail()->total_remaining)->toBe(12)
        ->and((int) $itemSummaries->firstOrFail()->active_lots_count)->toBe(2)
        ->and($locationSummaries)->toHaveCount(1)
        ->and((int) $locationSummaries->firstOrFail()->total_remaining)->toBe(12)
        ->and((int) $locationSummaries->firstOrFail()->active_lots_count)->toBe(2)
        ->and($material->inventoryItemStockSummaries)->toHaveCount(1)
        ->and((int) $material->inventoryItemStockSummaries->firstOrFail()->total_remaining)->toBe(12)
        ->and($warehouse->inventoryLocationStockSummaries)->toHaveCount(1)
        ->and((int) $warehouse->inventoryLocationStockSummaries->firstOrFail()->total_remaining)->toBe(12);
});

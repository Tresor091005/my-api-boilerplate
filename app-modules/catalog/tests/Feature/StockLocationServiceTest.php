<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Data\StockLocationData;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Services\StockLocationService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Inventory\Exceptions\InventoryLocationException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Models\Address;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->service = app(StockLocationService::class);
});

it('creates a stock location with one address and its inventory adapter', function (): void {
    $location = $this->service->create(StockLocationData::fromArray([
        'name'      => 'Main Warehouse',
        'is_active' => true,
        'address'   => [
            'line'    => '1 Main Street',
            'city'    => 'Cotonou',
            'country' => 'Benin',
        ],
    ]));
    expect($location->handle)->toBe('main-warehouse')
        ->and($location->address)->not->toBeNull()
        ->and($location->address->is_primary)->toBeTrue()
        ->and($location->inventoryLocation)->not->toBeNull()
        ->and($location->inventoryLocation->is_active)->toBeTrue();
});

it('creates the inventory location with the requested activity in one configuration', function (): void {
    $location = $this->service->create(StockLocationData::fromArray([
        'name'      => 'Inactive Warehouse',
        'is_active' => false,
    ]));

    expect($location->is_active)->toBeFalse()
        ->and($location->inventoryLocation->is_active)->toBeFalse();
});

it('replaces or removes the optional address and synchronizes activity', function (): void {
    $location = $this->service->create(StockLocationData::fromArray([
        'name'    => 'Reserve Warehouse',
        'address' => [
            'line'    => '2 Reserve Street',
            'city'    => 'Porto-Novo',
            'country' => 'Benin',
        ],
    ]));
    $originalAddressId = $location->address->id;

    $updated = $this->service->update($location, StockLocationData::fromArray([
        'address' => [
            'line'    => '3 New Street',
            'city'    => 'Abomey-Calavi',
            'country' => 'Benin',
        ],
    ], missingFields: ['name', 'is_active']));

    expect($updated->address->line)->toBe('3 New Street')
        ->and($updated->address->city)->toBe('Abomey-Calavi');

    $updated = $this->service->update($updated, StockLocationData::fromArray([
        'is_active' => false,
        'address'   => null,
    ], missingFields: ['name']));

    expect(Address::withTrashed()->whereKey($originalAddressId)->value('deleted_at'))->not->toBeNull()
        ->and($updated->address()->exists())->toBeFalse();
    expect($updated->address)->toBeNull()
        ->and($updated->inventoryLocation->is_active)->toBeFalse();
});

it('allows deleting an empty location and preserves its inventory adapter history', function (): void {
    $location = $this->service->create(StockLocationData::fromArray(['name' => 'Empty Warehouse']));
    $inventoryLocation = $location->inventoryLocation()->firstOrFail();

    $this->service->delete($location);

    expect(StockLocation::withTrashed()->find($location->id)->deleted_at)->not->toBeNull()
        ->and(InventoryLocation::withTrashed()->find($inventoryLocation->id)->deleted_at)->not->toBeNull();
});

it('rejects deleting a location with active stock', function (): void {
    $location = $this->service->create(StockLocationData::fromArray(['name' => 'Used Warehouse']));
    $unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
    $unit = Unit::factory()->create(['organization_id' => null, 'group_id' => $unitGroup->id]);
    $currency = Currency::query()->firstOrCreate(['code' => 'XOF'], [
        'name'      => 'West African CFA franc',
        'symbol'    => 'F',
        'precision' => 0,
    ]);
    $inventoryItem = InventoryItem::factory()->create([
        'organization_id' => $this->organizationId,
        'base_unit_code'  => $unit->code,
    ]);

    InventoryStock::factory()->create([
        'organization_id' => $this->organizationId,
        'item_id'         => $inventoryItem->id,
        'location_id'     => $location->inventoryLocation->id,
        'currency_code'   => $currency->code,
        'base_unit_code'  => $unit->code,
        'quantity'        => 1,
        'remaining'       => 1,
    ]);

    expect(fn () => $this->service->delete($location))
        ->toThrow(InventoryLocationException::class);
});

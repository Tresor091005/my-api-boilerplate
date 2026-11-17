<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\Registries\FeatureRegistry;
use Lahatre\Catalog\Contracts\CatalogInterface;
use Lahatre\Catalog\Data\StockLocationData;
use Lahatre\Catalog\Data\StockLocationFilterData;
use Lahatre\Catalog\Enums\StockLocationKind;
use Lahatre\Catalog\Exceptions\StockLocationException;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Services\StockLocationService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Inventory\Exceptions\InventoryLocationException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->locations = app(StockLocationService::class);
    $this->warehouse = $this->locations->create(StockLocationData::fromArray(['name' => 'Main Warehouse']));
});

function customerHoldOf(string $warehouseId): StockLocation
{
    return DB::transaction(fn (): StockLocation => app(CatalogInterface::class)->customerHoldLocation(currentOrganizationId(), $warehouseId));
}

function stockInLocation(StockLocation $location): void
{
    $unit = Unit::factory()->create(['organization_id' => null, 'group_id' => UnitGroup::factory()->create(['organization_id' => null])->id]);
    Currency::query()->firstOrCreate(['code' => 'XOF'], ['symbol' => 'F', 'precision' => 0]);
    InventoryStock::factory()->create([
        'organization_id' => currentOrganizationId(),
        'item_id'         => InventoryItem::factory()->create(['organization_id' => currentOrganizationId(), 'base_unit_code' => $unit->code])->id,
        'location_id'     => $location->inventoryLocation()->firstOrFail()->id,
        'currency_code'   => 'XOF',
        'base_unit_code'  => $unit->code,
        'quantity'        => 1,
        'remaining'       => 1,
    ]);
}

it('creates the customer hold location of a warehouse once, active, beside it', function (): void {
    $hold = customerHoldOf($this->warehouse->id);

    expect(customerHoldOf($this->warehouse->id)->id)->toBe($hold->id)
        ->and($hold->kind)->toBe(StockLocationKind::CustomerHold)
        ->and($hold->held_for_location_id)->toBe($this->warehouse->id)
        ->and($hold->name)->toBe('Main Warehouse (held for customers)')
        ->and($hold->handle)->toBe('main-warehouse-hold')
        ->and($hold->inventoryLocation()->firstOrFail()->is_active)->toBeTrue()
        ->and(StockLocation::query()->where('held_for_location_id', $this->warehouse->id)->count())->toBe(1);
});

it('refuses a hold location for an inactive warehouse or for another hold location', function (): void {
    $inactive = $this->locations->create(StockLocationData::fromArray(['name' => 'Closed', 'is_active' => false]));
    $hold = customerHoldOf($this->warehouse->id);

    expect(fn (): StockLocation => customerHoldOf($inactive->id))->toThrow(ModelNotFoundException::class)
        ->and(fn (): StockLocation => customerHoldOf($hold->id))->toThrow(ModelNotFoundException::class);
});

it('does not count hold locations against the plan', function (): void {
    customerHoldOf($this->warehouse->id);

    expect(app(FeatureRegistry::class)->count('catalog.stock_locations', $this->organizationId))->toBe(1);
});

it('lets nobody change or delete a hold location', function (): void {
    $hold = customerHoldOf($this->warehouse->id);

    expect(fn (): StockLocation => $this->locations->update($hold, StockLocationData::fromArray(['name' => 'Mine'], missingFields: ['is_active', 'address'])))
        ->toThrow(StockLocationException::class, __('catalog::exceptions.stock_location_managed_by_system'))
        ->and(fn () => $this->locations->delete($hold))->toThrow(StockLocationException::class);
});

it('deletes a warehouse with its empty hold location, and refuses while the hold location keeps stock', function (): void {
    $other = $this->locations->create(StockLocationData::fromArray(['name' => 'Other Warehouse']));
    stockInLocation(customerHoldOf($other->id));
    $emptyHold = customerHoldOf($this->warehouse->id);

    $this->locations->delete($this->warehouse);

    expect(StockLocation::query()->whereKey($emptyHold->id)->exists())->toBeFalse()
        ->and(fn () => $this->locations->delete($other))->toThrow(InventoryLocationException::class)
        ->and(StockLocation::query()->whereKey($other->id)->exists())->toBeTrue();
});

it('filters the locations by kind', function (): void {
    customerHoldOf($this->warehouse->id);
    $kinds = fn (?string $kind): array => $this->locations->paginate(StockLocationFilterData::fromArray(['kind' => $kind]))
        ->getCollection()->map(fn (StockLocation $location): string => $location->kind->value)->all();

    expect($kinds('warehouse'))->toBe(['warehouse'])
        ->and($kinds('customer_hold'))->toBe(['customer_hold'])
        ->and($kinds(null))->toHaveCount(2);
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Data\StockTransferData;
use Lahatre\Catalog\Enums\StockTransferStatus;
use Lahatre\Catalog\Exceptions\StockTransferException;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Models\StockTransfer;
use Lahatre\Catalog\Models\StockTransferLine;
use Lahatre\Catalog\Services\StockTransferService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Exceptions\ReversalException;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->service = app(StockTransferService::class);
    $this->inventory = app(InventoryInterface::class);
    $this->group = UnitGroup::factory()->create(['organization_id' => null]);
    $this->unit = Unit::factory()->create(['organization_id' => null, 'group_id' => $this->group->id, 'ratio' => 1]);
    app(UnitCache::class)->rewarmUnits();
    Currency::query()->firstOrCreate(['code' => 'XOF'], ['name' => 'West African CFA franc', 'symbol' => 'F', 'precision' => 0]);
    $this->source = transferLocation('Source');
    $this->destination = transferLocation('Destination');
    $product = Product::factory()->create(['organization_id' => $this->organizationId]);
    $this->variant = createCatalogProductVariant(['organization_id' => $this->organizationId, 'product_id' => $product->id], [
        'organization_id' => $this->organizationId, 'unit_group_id' => $this->group->id, 'sku' => 'TRANSFER-ITEM',
    ]);
    $this->catalogItem = CatalogItem::query()->findOrFail($this->variant->id);
    $this->inventoryItem = $this->inventory->createItem($this->catalogItem);
});

it('creates editable drafts with relational transfer lines', function (): void {
    $transfer = $this->service->create(transferData($this->source, $this->destination, $this->catalogItem->id, $this->unit->code));

    expect($transfer->status)->toBe(StockTransferStatus::Draft)
        ->and($transfer->lines)->toHaveCount(1)
        ->and($transfer->lines->first()->catalog_item_id)->toBe($this->catalogItem->id);
});

it('persists selected stock ids when transfer lines are bulk inserted', function (): void {
    $stock = transferStock($this->source, 10, $this->inventoryItem->id, $this->unit->code);

    $transfer = $this->service->create(transferData(
        $this->source,
        $this->destination,
        $this->catalogItem->id,
        $this->unit->code,
        [$stock->id],
    ));

    expect($transfer->lines()->firstOrFail()->stock_ids)->toBe([$stock->id]);
});

it('creates coherent transfer lines with a concrete item and compatible unit', function (): void {
    $line = StockTransferLine::factory()->create();
    /** @var CatalogItem $catalogItem */
    $catalogItem = $line->catalogItem()->firstOrFail();
    /** @var Unit $unit */
    $unit = Unit::query()->where('code', $line->display_unit_code)->firstOrFail();
    $item = $line->item()->first();

    expect($item)
        ->toBeInstanceOf(ProductVariant::class)
        ->and($item?->getKey())->toBe($catalogItem->id)
        ->and($unit->group_id)->toBe($catalogItem->unit_group_id);
});

it('completes one atomic inventory transfer and persists its transaction', function (): void {
    transferStock($this->source, 10, $this->inventoryItem->id, $this->unit->code);
    $transfer = $this->service->create(transferData($this->source, $this->destination, $this->catalogItem->id, $this->unit->code));

    $completed = $this->service->complete($transfer);

    expect($completed->status)->toBe(StockTransferStatus::Completed)
        ->and($completed->inventory_transaction_id)->not->toBeNull()
        ->and(InventoryTransaction::query()->count())->toBe(1)
        ->and(destinationStock($this->destination, $this->inventoryItem->id)->remaining)->toBe(10);
});

it('cancels by reversing the exact completed inventory transaction', function (): void {
    transferStock($this->source, 10, $this->inventoryItem->id, $this->unit->code);
    $transfer = $this->service->complete($this->service->create(transferData($this->source, $this->destination, $this->catalogItem->id, $this->unit->code)));

    $cancelled = $this->service->cancel($transfer);

    expect($cancelled->status)->toBe(StockTransferStatus::Cancelled)
        ->and($cancelled->reversal_transaction_id)->not->toBeNull()
        ->and((int) InventoryStock::query()->where('location_id', $this->source->inventoryLocation()->firstOrFail()->id)->sum('remaining'))->toBe(10)
        ->and((int) destinationStock($this->destination, $this->inventoryItem->id)->remaining)->toBe(0)
        ->and(InventoryTransaction::query()->count())->toBe(2);
});

it('keeps a completed transfer unchanged when its destination stock was used', function (): void {
    transferStock($this->source, 10, $this->inventoryItem->id, $this->unit->code);
    $transfer = $this->service->complete($this->service->create(transferData($this->source, $this->destination, $this->catalogItem->id, $this->unit->code)));
    $destinationStock = destinationStock($this->destination, $this->inventoryItem->id);
    $destinationStock->update(['remaining' => 0]);

    expect(fn () => $this->service->cancel($transfer))->toThrow(ReversalException::class)
        ->and($transfer->refresh()->status)->toBe(StockTransferStatus::Completed)
        ->and(InventoryTransaction::query()->count())->toBe(1);
});

it('allows drafts to be updated and deleted but protects completed transfers', function (): void {
    $transfer = $this->service->create(transferData($this->source, $this->destination, $this->catalogItem->id, $this->unit->code));
    $updated = $this->service->update($transfer, transferData($this->destination, $this->source, $this->catalogItem->id, $this->unit->code));

    expect($updated->source_location_id)->toBe($this->destination->id)
        ->and($updated->status)->toBe(StockTransferStatus::Draft);

    $this->service->delete($updated);
    expect(StockTransfer::query()->whereKey($updated->id)->exists())->toBeFalse();

    transferStock($this->source, 10, $this->inventoryItem->id, $this->unit->code);
    $completed = $this->service->complete($this->service->create(transferData($this->source, $this->destination, $this->catalogItem->id, $this->unit->code)));
    expect(fn () => $this->service->update($completed, transferData($this->source, $this->destination, $this->catalogItem->id, $this->unit->code)))
        ->toThrow(StockTransferException::class);
});

function transferLocation(string $name): StockLocation
{
    $location = StockLocation::factory()->create(['organization_id' => currentOrganizationId(), 'name' => $name]);
    app(InventoryInterface::class)->createLocation($location);

    return $location;
}

function transferData(
    StockLocation $source,
    StockLocation $destination,
    string $catalogItemId,
    string $unitCode,
    array $stockIds = [],
): StockTransferData {
    return StockTransferData::fromArray([
        'source_location_id'      => $source->id,
        'destination_location_id' => $destination->id,
        'lines'                   => [[
            'catalog_item_id' => $catalogItemId,
            'quantity'        => 10,
            'unit_code'       => $unitCode,
            'stock_ids'       => $stockIds,
        ]],
    ]);
}

function transferStock(StockLocation $location, int $quantity, string $inventoryItemId, string $unitCode): InventoryStock
{
    return InventoryStock::factory()->create([
        'organization_id' => currentOrganizationId(),
        'item_id'         => $inventoryItemId,
        'location_id'     => $location->inventoryLocation()->firstOrFail()->id,
        'quantity'        => $quantity, 'remaining' => $quantity, 'unit_cost' => 1000,
        'currency_code'   => 'XOF', 'base_unit_code' => $unitCode,
    ]);
}

function destinationStock(StockLocation $location, string $inventoryItemId): InventoryStock
{
    return InventoryStock::query()
        ->where('item_id', $inventoryItemId)
        ->where('location_id', $location->inventoryLocation()->firstOrFail()->id)
        ->firstOrFail();
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Services\InventoryService;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(InventoryService::class);

    // Setup Master Data
    $this->currency = Currency::factory()->create();
    $this->group = UnitGroup::factory()->create();
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);

    // Setup Inventory Data
    $this->item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $this->location = InventoryLocation::factory()->create();
});

it('fails if multiple currencies are used in one transaction', function (): void {
    $currency2 = Currency::factory()->create();
    $item2 = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);

    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            ['type' => 'in', 'item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 10, 'unit_code' => $this->unit->code, 'unit_cost' => 10, 'currency_code' => $this->currency->code],
            ['type' => 'in', 'item_id' => $item2->id, 'location_id' => $this->location->id, 'quantity' => 5, 'unit_code' => $this->unit->code, 'unit_cost' => 10, 'currency_code' => $currency2->code],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(ValidationException::class, 'All movements in a transaction must use the same currency code.');

it('fails if unit does not belong to the same group as item base unit', function (): void {
    $otherGroup = UnitGroup::factory()->create();
    $otherUnit = Unit::factory()->create(['group_id' => $otherGroup->id]);

    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            ['type' => 'in', 'item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 10, 'unit_code' => $otherUnit->code, 'unit_cost' => 10, 'currency_code' => $this->currency->code],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, "Unit {$otherUnit->code} belongs to a different group than item base unit {$this->item->base_unit_code}.");
});

it('fails if manual strategy is used without providing stock_ids', function (): void {
    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'type'        => 'out',
                'item_id'     => $this->item->id,
                'location_id' => $this->location->id,
                'quantity'    => 10,
                'unit_code'   => $this->unit->code,
                'strategy'    => DeductionStrategy::Manual->value,
                // stock_ids are missing
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, 'Stock IDs are required when strategy is manual.');
});

it('fails if a provided stock_id does not belong to the correct item and location', function (): void {
    $otherItem = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $stockForOtherItem = InventoryStock::factory()->for($otherItem, 'item')->for($this->location, 'location')->create();

    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'type'        => 'out',
                'item_id'     => $this->item->id, // This item
                'location_id' => $this->location->id,
                'quantity'    => 10,
                'unit_code'   => $this->unit->code,
                'strategy'    => DeductionStrategy::Manual->value,
                'stock_ids'   => [$stockForOtherItem->id], // But stock from other item
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, "Stock ID {$stockForOtherItem->id} does not belong to the correct item and location.");
});

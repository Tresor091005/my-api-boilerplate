<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Services\InventoryService;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Inventory\Enums\DeductionStrategy;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(InventoryService::class);

    // Setup Master Data
    $this->currency = Currency::factory()->create();
    $this->group = UnitGroup::factory()->create();
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);

    // Setup Inventory Data
    $this->item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code, 'deduction_strategy' => DeductionStrategy::Fifo]);
    $this->locA = InventoryLocation::factory()->create();
    $this->locB = InventoryLocation::factory()->create();
});

it('successfully processes a balanced transfer with lot splitting', function (): void {
    // GIVEN two stock lots at location A
    $lot1 = InventoryStock::factory()->for($this->item, 'item')->for($this->locA, 'location')->create(['quantity' => 50, 'remaining' => 50, 'unit_cost' => 1000]);
    $lot2 = InventoryStock::factory()->for($this->item, 'item')->for($this->locA, 'location')->create(['quantity' => 50, 'remaining' => 50, 'unit_cost' => 1200]);

    // WHEN we transfer 70 units from A to B
    $payload = [
        'reference_type'   => 'transfer_order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Transfer->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->locA->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 70,
                'unit_code'   => $this->unit->code,
            ],
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->locB->id,
                'type'        => MovementType::In->value,
                'quantity'    => 70,
                'unit_code'   => $this->unit->code,
            ],
        ],
    ];

    $this->service->recordTransaction($payload);

    // THEN stock at location A is depleted correctly
    expect((float) $this->locA->stocks()->sum('remaining'))->toEqual(30.0);

    // AND stock at location B has increased by 70
    expect((float) $this->locB->stocks()->sum('remaining'))->toEqual(70.0);

    // AND the new stock at location B has the correct inherited costs
    $newStockAtB = $this->locB->stocks()->latest()->first();
    expect($newStockAtB->unit_cost)->toBeIn([1000, 1200]);
});

it('fails a transfer if it is not balanced', function (): void {
    $payload = [
        'reference_type'   => 'transfer_order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Transfer->value,
        'movements'        => [
            ['item_id' => $this->item->id, 'location_id' => $this->locA->id, 'type' => 'out', 'quantity' => 10, 'unit_code' => $this->unit->code],
            ['item_id' => $this->item->id, 'location_id' => $this->locB->id, 'type' => 'in', 'quantity' => 5, 'unit_code' => $this->unit->code],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(ValidationException::class, 'Transfer imbalance for item');

it('fails a transfer if cost or currency is provided for an IN movement', function ($field, $value): void {
    $movement = [
        'item_id'     => $this->item->id,
        'location_id' => $this->locB->id,
        'type'        => MovementType::In->value,
        'quantity'    => 10,
        'unit_code'   => $this->unit->code,
    ];
    $movement[$field] = $value;

    $payload = [
        'reference_type'   => 'transfer_order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Transfer->value,
        'movements'        => [
            ['item_id' => $this->item->id, 'location_id' => $this->locA->id, 'type' => 'out', 'quantity' => 10, 'unit_code' => $this->unit->code],
            $movement,
        ],
    ];

    $this->service->recordTransaction($payload);
})->with([
    'unit_cost' => ['unit_cost', 100],
    'currency' => ['currency_code', 'EUR'],
])->throws(ValidationException::class);

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
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

it('successfully processes an OUT transaction using FIFO strategy', function (): void {
    // GIVEN two stock lots, lot1 is older
    $lot1 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create(['quantity' => 50, 'remaining' => 50, 'created_at' => now()->subDay()]);
    $lot2 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create(['quantity' => 50, 'remaining' => 50, 'created_at' => now()]);

    // WHEN we deduct 70 units
    $payload = [
        'reference_type'   => 'sale_order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->location->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 70,
                'unit_code'   => $this->unit->code,
                'strategy'    => DeductionStrategy::Fifo->value,
            ],
        ],
    ];

    $this->service->recordTransaction($payload);

    // THEN it should create two OUT movements, one for each lot
    $this->assertDatabaseCount('inventory_movements', 2);

    // AND lot1 should be fully depleted
    expect($lot1->refresh()->remaining)->toBe(0);

    // AND lot2 should have 30 remaining
    expect($lot2->refresh()->remaining)->toBe(30);
});

it('successfully processes an OUT transaction using FEFO strategy', function (): void {
    // GIVEN two stock lots, lot2 expires sooner
    $lot1 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create(['quantity' => 50, 'remaining' => 50, 'peremption_date' => now()->addDays(10)]);
    $lot2 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create(['quantity' => 50, 'remaining' => 50, 'peremption_date' => now()->addDays(5)]);

    // WHEN we deduct 70 units
    $payload = [
        'reference_type'   => 'sale_order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->location->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 70,
                'unit_code'   => $this->unit->code,
                'strategy'    => DeductionStrategy::Fefo->value,
            ],
        ],
    ];

    $this->service->recordTransaction($payload);

    // THEN lot2 (expires sooner) should be fully depleted
    expect($lot2->refresh()->remaining)->toBe(0);

    // AND lot1 should have 30 remaining
    expect($lot1->refresh()->remaining)->toBe(30);
});

it('successfully processes an OUT transaction using Manual strategy', function (): void {
    // GIVEN two stock lots
    $lot1 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create(['quantity' => 50, 'remaining' => 50]);
    $lot2 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create(['quantity' => 50, 'remaining' => 50]);

    // WHEN we manually deduct 30 units from lot2
    $payload = [
        'reference_type'   => 'sale_order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->location->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 30,
                'unit_code'   => $this->unit->code,
                'strategy'    => DeductionStrategy::Manual->value,
                'stock_ids'   => [$lot2->id],
            ],
        ],
    ];

    $this->service->recordTransaction($payload);

    // THEN it should create one OUT movement
    $this->assertDatabaseCount('inventory_movements', 1);
    $movement = InventoryMovement::first();
    expect($movement->stock_id)->toBe($lot2->id);

    // AND lot1 should be untouched
    expect($lot1->refresh()->remaining)->toBe(50);

    // AND lot2 should have 20 remaining
    expect($lot2->refresh()->remaining)->toBe(20);
});

it('throws an exception for an OUT transaction if stock is insufficient', function (): void {
    // GIVEN there is no stock
    // WHEN we try to deduct
    $payload = [
        'reference_type'   => 'sale_order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            ['item_id' => $this->item->id, 'location_id' => $this->location->id, 'type' => 'out', 'quantity' => 10, 'unit_code' => $this->unit->code, 'strategy' => DeductionStrategy::Fifo->value],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(InsufficientStockException::class);

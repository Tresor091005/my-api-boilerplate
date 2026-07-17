<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Services\InventoryService;
use Lahatre\Inventory\Tests\Concerns\InteractsWithInventoryTestFixtures;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithInventoryTestFixtures::class);

beforeEach(function (): void {
    $this->ensureInventoryTestTables();
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
        'idempotency_key'  => fake()->uuid(),
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
        'idempotency_key'  => fake()->uuid(),
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
        'idempotency_key'  => fake()->uuid(),
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
    'currency'  => ['currency_code', 'EUR'],
])->throws(ValidationException::class);

it('distributes transfer batches correctly across multiple source and destination locations', function (): void {
    $locC = InventoryLocation::factory()->create();
    $locD = InventoryLocation::factory()->create();

    $lotA1 = InventoryStock::factory()
        ->for($this->item, 'item')
        ->for($this->locA, 'location')
        ->create(['quantity' => 25, 'remaining' => 25, 'unit_cost' => 1000, 'created_at' => now()->subDays(2)]);
    $lotA2 = InventoryStock::factory()
        ->for($this->item, 'item')
        ->for($this->locA, 'location')
        ->create(['quantity' => 25, 'remaining' => 25, 'unit_cost' => 1100, 'created_at' => now()->subDay()]);
    $lotC1 = InventoryStock::factory()
        ->for($this->item, 'item')
        ->for($locC, 'location')
        ->create(['quantity' => 20, 'remaining' => 20, 'unit_cost' => 1200, 'created_at' => now()]);

    $payload = [
        'reference_type'   => 'transfer_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Transfer->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->locA->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 40,
                'unit_code'   => $this->unit->code,
            ],
            [
                'item_id'     => $this->item->id,
                'location_id' => $locC->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 20,
                'unit_code'   => $this->unit->code,
            ],
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->locB->id,
                'type'        => MovementType::In->value,
                'quantity'    => 30,
                'unit_code'   => $this->unit->code,
            ],
            [
                'item_id'     => $this->item->id,
                'location_id' => $locD->id,
                'type'        => MovementType::In->value,
                'quantity'    => 30,
                'unit_code'   => $this->unit->code,
            ],
        ],
    ];

    $this->service->recordTransaction($payload);

    expect($lotA1->refresh()->remaining)->toBe(0)
        ->and($lotA2->refresh()->remaining)->toBe(10)
        ->and($lotC1->refresh()->remaining)->toBe(0)
        ->and((int) $this->locB->stocks()->sum('remaining'))->toBe(30)
        ->and((int) $locD->stocks()->sum('remaining'))->toBe(30);

    $incomingAtB = InventoryMovement::query()
        ->where('movement_type', MovementType::In)
        ->where('location_id', $this->locB->id)
        ->orderBy('created_at')
        ->get();
    $incomingAtD = InventoryMovement::query()
        ->where('movement_type', MovementType::In)
        ->where('location_id', $locD->id)
        ->orderBy('created_at')
        ->get();

    expect($incomingAtB)->toHaveCount(2)
        ->and($incomingAtB->pluck('quantity')->all())->toBe([25, 5])
        ->and($incomingAtB->pluck('unit_cost')->all())->toBe([1000, 1100])
        ->and($incomingAtD)->toHaveCount(2)
        ->and($incomingAtD->pluck('quantity')->all())->toBe([10, 20])
        ->and($incomingAtD->pluck('unit_cost')->all())->toBe([1100, 1200]);
});

it('merges source stock metadata and movement metadata into destination stock during transfer', function (): void {
    InventoryStock::factory()->for($this->item, 'item')->for($this->locA, 'location')->create([
        'quantity'  => 10,
        'remaining' => 10,
        'metadata'  => ['batch' => 'LOT-001', 'supplier' => 'A'],
        'unit_cost' => 1000,
    ]);

    $this->service->recordTransaction([
        'reference_type'   => 'transfer_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Transfer->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->locA->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 10,
                'unit_code'   => $this->unit->code,
            ],
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->locB->id,
                'type'        => MovementType::In->value,
                'quantity'    => 10,
                'unit_code'   => $this->unit->code,
                'metadata'    => ['reason' => 'rebalance'],
            ],
        ],
    ]);

    $destinationStock = $this->locB->stocks()->firstOrFail();

    expect($destinationStock->metadata['batch'])->toBe('LOT-001')
        ->and($destinationStock->metadata['supplier'])->toBe('A')
        ->and($destinationStock->metadata['reason'])->toBe('rebalance');
});

it('handles rounding differences in transfers between units of different precisions', function (): void {
    $kgUnit = Unit::factory()->create(['ratio' => 1000, 'group_id' => $this->group->id]);
    InventoryStock::factory()->for($this->item, 'item')->for($this->locA, 'location')->create([
        'quantity'  => 1000,
        'remaining' => 1000,
        'unit_cost' => 1000,
    ]);

    $this->service->recordTransaction([
        'reference_type'   => 'transfer_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Transfer->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->locA->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 0.5,
                'unit_code'   => $kgUnit->code,
            ],
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->locB->id,
                'type'        => MovementType::In->value,
                'quantity'    => 500,
                'unit_code'   => $this->unit->code,
            ],
        ],
    ]);

    expect((int) $this->locA->stocks()->sum('remaining'))->toBe(500)
        ->and((int) $this->locB->stocks()->sum('remaining'))->toBe(500);
});

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\AdjustmentAverageCostUnavailableException;
use Lahatre\Inventory\Exceptions\AdjustmentNoOpException;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
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
    currentTestCase()->configureInventoryCurrency($this->currency->code);
    $this->group = UnitGroup::factory()->create();
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);

    // Setup Inventory Data
    $this->item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code, 'deduction_strategy' => DeductionStrategy::Fifo]);
    $this->location = InventoryLocation::factory()->create();
});

it('successfully processes an adjustment UP transaction', function (): void {
    // GIVEN there is a stock of 50
    InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'      => 50,
        'remaining'     => 50,
        'unit_cost'     => 1200,
        'currency_code' => $this->currency->code,
        'metadata'      => ['source' => 'original'],
    ]);

    // WHEN we adjust the quantity to 80
    $payload = [
        'reference_type'   => 'stock_take',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Adjustment->value,
        'movements'        => [
            [
                'item_id'        => $this->item->id,
                'location_id'    => $this->location->id,
                'quantity'       => 80,
                'unit_code'      => $this->unit->code,
                'total_cost'     => 300.00,
                'currency_code'  => $this->currency->code,
                'stock_metadata' => ['batch' => 'ADJ-LOT-1'],
            ],
        ],
    ];

    $this->service->recordTransaction($payload);

    // THEN a new stock lot of 30 should use the existing average cost.
    $this->assertDatabaseCount('inventory_stocks', 2);
    $this->assertDatabaseHas('inventory_stocks', ['quantity' => 30, 'remaining' => 30, 'unit_cost' => 1200, 'cost_remainder' => 0]);
    expect(InventoryStock::query()->where('quantity', 30)->firstOrFail()->metadata)
        ->toBe(['batch' => 'ADJ-LOT-1']);

    // AND total stock should be 80
    expect((float) $this->location->stocks()->sum('remaining'))->toEqual(80.0);
});

it('uses the functional currency average for an adjustment', function (): void {
    InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'      => 50,
        'remaining'     => 50,
        'unit_cost'     => 1000,
        'currency_code' => $this->currency->code,
    ]);
    InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'      => 50,
        'remaining'     => 50,
        'unit_cost'     => 2000,
        'currency_code' => $this->currency->code,
    ]);

    $this->service->recordTransaction([
        'reference_type'   => 'stock_take',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Adjustment->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'quantity'    => 120,
            'unit_code'   => $this->unit->code,
            'total_cost'  => 1.00,
        ]],
    ]);

    $this->assertDatabaseHas('inventory_stocks', [
        'quantity'      => 20,
        'unit_cost'     => 1500,
        'currency_code' => $this->currency->code,
    ]);
});

it('uses the exact remaining value and rounds the added cost down', function (): void {
    InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'       => 3,
        'remaining'      => 3,
        'unit_cost'      => 3333,
        'cost_remainder' => 2,
        'currency_code'  => $this->currency->code,
    ]);

    $this->service->recordTransaction([
        'reference_type'   => 'stock_take',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Adjustment->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'quantity'    => 5,
            'unit_code'   => $this->unit->code,
        ]],
    ]);

    $this->assertDatabaseHas('inventory_stocks', [
        'quantity'       => 2,
        'unit_cost'      => 3333,
        'cost_remainder' => 1,
    ]);
    $this->assertDatabaseHas('inventory_movements', [
        'movement_type' => 'in',
        'quantity'      => 2,
        'total_cost'    => 6667,
    ]);
});

it('rejects a positive adjustment when no stock exists for the functional currency', function (): void {
    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'stock_take',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Adjustment->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'quantity'    => 10,
            'unit_code'   => $this->unit->code,
        ]],
    ]))->toThrow(AdjustmentAverageCostUnavailableException::class);
});

it('does not require a currency for a positive adjustment', function (): void {
    InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'      => 50,
        'remaining'     => 50,
        'unit_cost'     => 1200,
        'currency_code' => $this->currency->code,
    ]);

    $this->service->recordTransaction([
        'reference_type'   => 'stock_take',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Adjustment->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'quantity'    => 60,
            'unit_code'   => $this->unit->code,
        ]],
    ]);

    $this->assertDatabaseHas('inventory_stocks', [
        'quantity'      => 10,
        'unit_cost'     => 1200,
        'currency_code' => $this->currency->code,
    ]);
});

it('successfully processes an adjustment DOWN transaction', function (): void {
    // GIVEN there is a stock of 50
    $stock = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'  => 50,
        'remaining' => 50,
        'metadata'  => ['source' => 'original'],
    ]);

    // WHEN we adjust the quantity to 20
    $payload = [
        'reference_type'   => 'stock_take',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Adjustment->value,
        'movements'        => [
            [
                'item_id'        => $this->item->id,
                'location_id'    => $this->location->id,
                'quantity'       => 20,
                'unit_code'      => $this->unit->code,
                'total_cost'     => 999.00,
                'currency_code'  => $this->currency->code,
                'stock_metadata' => ['source' => 'ignored'],
            ],
        ],
    ];

    $this->service->recordTransaction($payload);

    // THEN total stock should be 20
    expect((float) $this->location->stocks()->sum('remaining'))->toEqual(20.0);
    expect($stock->refresh()->metadata)->toBe(['source' => 'original']);
    expect($stock->movements()->where('movement_type', 'out')->latest('created_at')->firstOrFail()->currency_code)
        ->toBe($stock->currency_code);
});

it('fails an adjustment if target quantity is the same as current stock', function (): void {
    // GIVEN there is a stock of 50
    InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create(['quantity' => 50, 'remaining' => 50]);

    // WHEN we adjust the quantity to 50
    $payload = [
        'reference_type'   => 'stock_take',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Adjustment->value,
        'movements'        => [
            ['item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 50, 'unit_code' => $this->unit->code],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(AdjustmentNoOpException::class, 'The target quantity is already the current stock.');

it('reports the actual reduction when selected manual lots are insufficient', function (): void {
    $selectedStock = InventoryStock::factory()
        ->for($this->item, 'item')
        ->for($this->location, 'location')
        ->create(['quantity' => 5, 'remaining' => 5]);
    $otherStock = InventoryStock::factory()
        ->for($this->item, 'item')
        ->for($this->location, 'location')
        ->create(['quantity' => 5, 'remaining' => 5]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'stock_take',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Adjustment->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'quantity'    => 1,
            'unit_code'   => $this->unit->code,
            'strategy'    => DeductionStrategy::Manual->value,
            'stock_ids'   => [$selectedStock->id],
        ]],
    ]))->toThrow(InsufficientStockException::class, 'Requested: 9');

    expect($selectedStock->refresh()->remaining)->toBe(5)
        ->and($otherStock->refresh()->remaining)->toBe(5);
});

it('fails an adjustment for the same item and location in one transaction', function (): void {
    $payload = [
        'reference_type'   => 'stock_take',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Adjustment->value,
        'movements'        => [
            ['item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 50, 'unit_code' => $this->unit->code],
            ['item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 60, 'unit_code' => $this->unit->code],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(ValidationException::class, 'For Adjustment transactions, the same item cannot appear multiple times for the same location.');

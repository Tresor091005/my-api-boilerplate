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
        'idempotency_key'  => fake()->uuid(),
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
    $this->item->update(['is_expirable' => true]);

    // GIVEN two stock lots, lot2 expires sooner
    $lot1 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create(['quantity' => 50, 'remaining' => 50, 'expiration_date' => today()->addDays(10)]);
    $lot2 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create(['quantity' => 50, 'remaining' => 50, 'expiration_date' => today()->addDays(5)]);

    // WHEN we deduct 70 units
    $payload = [
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
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
        'idempotency_key'  => fake()->uuid(),
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
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            ['item_id' => $this->item->id, 'location_id' => $this->location->id, 'type' => 'out', 'quantity' => 10, 'unit_code' => $this->unit->code, 'strategy' => DeductionStrategy::Fifo->value],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(InsufficientStockException::class);

it('reuses the locked stock selection across multiple out movements for the same item and location', function (): void {
    $lot1 = InventoryStock::factory()
        ->for($this->item, 'item')
        ->for($this->location, 'location')
        ->create(['quantity' => 50, 'remaining' => 50, 'created_at' => now()->subDay()]);
    $lot2 = InventoryStock::factory()
        ->for($this->item, 'item')
        ->for($this->location, 'location')
        ->create(['quantity' => 50, 'remaining' => 50, 'created_at' => now()]);

    $payload = [
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->location->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 30,
                'unit_code'   => $this->unit->code,
                'strategy'    => DeductionStrategy::Fifo->value,
            ],
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->location->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 40,
                'unit_code'   => $this->unit->code,
                'strategy'    => DeductionStrategy::Fifo->value,
            ],
        ],
    ];

    $this->service->recordTransaction($payload);

    expect($lot1->refresh()->remaining)->toBe(0)
        ->and($lot2->refresh()->remaining)->toBe(30);

    $movementsByStock = InventoryMovement::query()
        ->orderBy('created_at')
        ->get()
        ->groupBy('stock_id');

    expect($movementsByStock->get($lot1->id))->toHaveCount(2)
        ->and($movementsByStock->get($lot1->id)->sum('quantity'))->toBe(50)
        ->and($movementsByStock->get($lot2->id))->toHaveCount(1)
        ->and($movementsByStock->get($lot2->id)->sum('quantity'))->toBe(20);
});

it('successfully resolves FIFO when no strategy is defined anywhere', function (): void {
    $item = InventoryItem::factory()->create([
        'base_unit_code'     => $this->unit->code,
        'deduction_strategy' => null,
    ]);

    $lot1 = InventoryStock::factory()->for($item, 'item')->for($this->location, 'location')->create(['quantity' => 40, 'remaining' => 40, 'created_at' => now()->subDay()]);
    $lot2 = InventoryStock::factory()->for($item, 'item')->for($this->location, 'location')->create(['quantity' => 40, 'remaining' => 40, 'created_at' => now()]);

    $this->service->recordTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $item->id,
            'location_id' => $this->location->id,
            'type'        => MovementType::Out->value,
            'quantity'    => 50,
            'unit_code'   => $this->unit->code,
        ]],
    ]);

    expect($lot1->refresh()->remaining)->toBe(0)
        ->and($lot2->refresh()->remaining)->toBe(30);
});

it('processes FEFO correctly with a mix of stocks having and not having expiration dates', function (): void {
    $this->item->update(['is_expirable' => true]);

    $lot1 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'        => 20,
        'remaining'       => 20,
        'expiration_date' => today()->addDays(10),
    ]);
    $lot2 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'        => 20,
        'remaining'       => 20,
        'expiration_date' => null,
    ]);
    $lot3 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'        => 20,
        'remaining'       => 20,
        'expiration_date' => today()->addDays(5),
    ]);

    $this->service->recordTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'type'        => MovementType::Out->value,
            'quantity'    => 30,
            'unit_code'   => $this->unit->code,
            'strategy'    => DeductionStrategy::Fefo->value,
        ]],
    ]);

    expect($lot3->refresh()->remaining)->toBe(0)
        ->and($lot1->refresh()->remaining)->toBe(10)
        ->and($lot2->refresh()->remaining)->toBe(20);
});

it('processes Manual strategy by depleting multiple specific stock IDs in the order provided', function (): void {
    $lot1 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'   => 30,
        'remaining'  => 30,
        'created_at' => now()->subDay(),
    ]);
    $lot2 = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'   => 30,
        'remaining'  => 30,
        'created_at' => now(),
    ]);

    $this->service->recordTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'type'        => MovementType::Out->value,
            'quantity'    => 40,
            'unit_code'   => $this->unit->code,
            'strategy'    => DeductionStrategy::Manual->value,
            'stock_ids'   => [$lot2->id, $lot1->id],
        ]],
    ]);

    $movements = InventoryMovement::query()->orderBy('created_at')->get();

    expect($movements->pluck('stock_id')->all())->toBe([$lot2->id, $lot1->id])
        ->and($lot2->refresh()->remaining)->toBe(0)
        ->and($lot1->refresh()->remaining)->toBe(20);
});

it('preserves original source stock metadata when performing an OUT movement', function (): void {
    $lot = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'  => 30,
        'remaining' => 30,
        'metadata'  => ['batch' => 'LOT-001'],
    ]);

    $this->service->recordTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'type'        => MovementType::Out->value,
            'quantity'    => 10,
            'unit_code'   => $this->unit->code,
        ]],
    ]);

    $movement = InventoryMovement::firstOrFail();

    expect($movement->stock->metadata)->toBe(['batch' => 'LOT-001'])
        ->and($movement->stock_metadata_snapshot)->toBe(['batch' => 'LOT-001'])
        ->and($lot->refresh()->metadata)->toBe(['batch' => 'LOT-001']);
});

it('consumes the stock cost remainder on the first OUT movement', function (): void {
    $stock = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'       => 10,
        'remaining'      => 10,
        'unit_cost'      => 1000,
        'cost_remainder' => 1,
        'currency_code'  => $this->currency->code,
    ]);

    $recordOut = fn (string $key, int $quantity) => $this->service->recordTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => $key,
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'type'        => MovementType::Out->value,
            'quantity'    => $quantity,
            'unit_code'   => $this->unit->code,
        ]],
    ]);

    $first = $recordOut(fake()->uuid(), 3);
    $second = $recordOut(fake()->uuid(), 2);

    expect($first->movements->firstOrFail()->total_cost)->toBe(3001)
        ->and($second->movements->firstOrFail()->total_cost)->toBe(2000)
        ->and($stock->refresh()->remaining)->toBe(5)
        ->and($stock->cost_remainder)->toBe(0);
});

it('throws InsufficientStockException with accurate available quantity in the exception message', function (): void {
    InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'  => 15,
        'remaining' => 15,
    ]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'type'        => MovementType::Out->value,
            'quantity'    => 20,
            'unit_code'   => $this->unit->code,
        ]],
    ]))->toThrow(InsufficientStockException::class, 'Available: 15');
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\IdempotencyKeyReuseException;
use Lahatre\Inventory\Exceptions\ReversalException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Services\InventoryService;
use Lahatre\Inventory\Services\Stock\ManageInventoryStockService;
use Lahatre\Inventory\Tests\Concerns\InteractsWithInventoryTestFixtures;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithInventoryTestFixtures::class);

beforeEach(function (): void {
    $this->ensureInventoryTestTables();
    $this->service = app(InventoryService::class);
    $this->stockService = app(ManageInventoryStockService::class);
    $this->currency = Currency::factory()->create();
    $this->group = UnitGroup::factory()->create();
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);
    $this->item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $this->location = InventoryLocation::factory()->create();
});

it('reverses an IN by consuming the original stock', function (): void {
    $original = $this->service->recordTransaction([
        'reference_type'   => 'purchase_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'metadata'         => ['source' => 'supplier'],
        'movements'        => [[
            'item_id'        => $this->item->id,
            'location_id'    => $this->location->id,
            'type'           => MovementType::In->value,
            'quantity'       => 10,
            'unit_code'      => $this->unit->code,
            'total_cost'      => 125.00,
            'currency_code'  => $this->currency->code,
            'metadata'       => ['received_by' => 'user-1'],
            'stock_metadata' => ['batch' => 'LOT-1'],
        ]],
    ]);

    $stock = InventoryStock::query()->firstOrFail();
    $reversal = $this->service->reverseTransaction($original->id, ['reason' => 'cancelled']);

    expect($reversal->transaction_type)->toBe(TransactionType::Out)
        ->and($reversal->reversal_of_transaction_id)->toBe($original->id)
        ->and($reversal->metadata)->toBe(['reason' => 'cancelled'])
        ->and($stock->refresh()->remaining)->toBe(0);

    $movement = $reversal->movements->firstOrFail();
    expect($movement->stock_id)->toBe($stock->id)
        ->and($movement->metadata)->toBe(['received_by' => 'user-1']);
});

it('reverses an OUT by creating a new stock from the outbound snapshot', function (): void {
    $stock = InventoryStock::factory()->for($this->item, 'item')->for($this->location, 'location')->create([
        'quantity'       => 10,
        'remaining'      => 10,
        'unit_cost'      => 1250,
        'cost_remainder' => 1,
        'currency_code'  => $this->currency->code,
        'metadata'       => ['batch' => 'LOT-1', 'status' => 'available'],
    ]);

    $original = $this->service->recordTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'type'        => MovementType::Out->value,
            'quantity'    => 4,
            'unit_code'   => $this->unit->code,
            'metadata'    => ['order_line_id' => 'LINE-1'],
        ]],
    ]);

    $out = $original->movements->firstOrFail();
    expect($out->stock_metadata_snapshot)->toBe(['batch' => 'LOT-1', 'status' => 'available']);

    $this->stockService->updateMetadata($stock, ['batch' => 'LOT-1', 'status' => 'quarantine']);
    $reversal = $this->service->reverseTransaction($original->id, ['reason' => 'return']);

    $newStock = InventoryStock::query()->where('id', '!=', $stock->id)->firstOrFail();
    expect($stock->refresh()->remaining)->toBe(6)
        ->and($newStock->quantity)->toBe(4)
        ->and($newStock->remaining)->toBe(4)
        ->and($newStock->unit_cost)->toBe(1250)
        ->and($newStock->cost_remainder)->toBe(1)
        ->and($newStock->metadata)->toBe(['batch' => 'LOT-1', 'status' => 'available'])
        ->and($reversal->transaction_type)->toBe(TransactionType::In)
        ->and($reversal->metadata)->toBe(['reason' => 'return']);
});

it('rejects reversing an adjustment', function (): void {
    $transaction = InventoryTransaction::factory()->create([
        'transaction_type' => TransactionType::Adjustment,
    ]);

    expect(fn () => $this->service->reverseTransaction($transaction->id))
        ->toThrow(ReversalException::class);
});

it('returns the same reversal on an idempotent retry', function (): void {
    $original = $this->service->recordTransaction([
        'reference_type'   => 'purchase_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'item_id'        => $this->item->id,
            'location_id'    => $this->location->id,
            'type'           => MovementType::In->value,
            'quantity'       => 5,
            'unit_code'      => $this->unit->code,
            'total_cost'      => 62.50,
            'currency_code'  => $this->currency->code,
            'stock_metadata' => ['batch' => 'LOT-1'],
        ]],
    ]);

    $first = $this->service->reverseTransaction($original->id, ['reason' => 'cancelled']);
    $second = $this->service->reverseTransaction($original->id, ['reason' => 'cancelled']);

    expect($second->id)->toBe($first->id)
        ->and(InventoryTransaction::query()->where('reversal_of_transaction_id', $original->id)->count())->toBe(1)
        ->and(InventoryMovement::query()->count())->toBe(2);
});

it('rejects a reversal request with different metadata on retry', function (): void {
    $original = $this->service->recordTransaction([
        'reference_type'   => 'purchase_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'type'          => MovementType::In->value,
            'quantity'      => 5,
            'unit_code'     => $this->unit->code,
            'total_cost'     => 62.50,
            'currency_code' => $this->currency->code,
        ]],
    ]);

    $this->service->reverseTransaction($original->id, ['reason' => 'cancelled']);

    expect(fn () => $this->service->reverseTransaction($original->id, ['reason' => 'mistake']))
        ->toThrow(IdempotencyKeyReuseException::class);
});

it('rejects stock metadata on an OUT movement', function (): void {
    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'        => $this->item->id,
            'location_id'    => $this->location->id,
            'type'           => MovementType::Out->value,
            'quantity'       => 1,
            'unit_code'      => $this->unit->code,
            'stock_metadata' => ['batch' => 'LOT-1'],
        ]],
    ]))->toThrow(ValidationException::class);
});

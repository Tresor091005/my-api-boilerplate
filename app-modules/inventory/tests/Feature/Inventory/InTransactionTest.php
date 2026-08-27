<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
use Lahatre\Organization\Models\ExchangeRate;

uses(RefreshDatabase::class, InteractsWithInventoryTestFixtures::class);

beforeEach(function (): void {
    $this->ensureInventoryTestTables();

    $this->service = app(InventoryService::class);

    // Setup Master Data
    $this->currency = Currency::factory()->create();
    currentTestCase()->configureInventoryCurrency($this->currency->code);
    $this->group = UnitGroup::factory()->create(['is_builtin' => false]);
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);

    // Setup Inventory Data
    $this->item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $this->location = InventoryLocation::factory()->create();
});

it('successfully processes a simple IN transaction', function (): void {
    $payload = [
        'reference_type'   => 'purchase_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'item_id'       => $this->item->id,
                'location_id'   => $this->location->id,
                'type'          => MovementType::In->value,
                'quantity'      => 100,
                'unit_code'     => $this->unit->code,
                'total_cost'    => 1550.00,
                'currency_code' => $this->currency->code,
            ],
        ],
    ];

    $tx = $this->service->recordTransaction($payload);

    // 1. Assert Transaction is created
    expect($tx->transaction_type)->toBe(TransactionType::In);

    // 2. Assert Stock is created
    $this->assertDatabaseHas('inventory_stocks', [
        'item_id'       => $this->item->id,
        'location_id'   => $this->location->id,
        'quantity'      => 100,
        'remaining'     => 100,
        'unit_cost'     => 1550,
        'currency_code' => $this->currency->code,
    ]);

    // 3. Assert Movement is created
    $this->assertDatabaseHas('inventory_movements', [
        'transaction_id' => $tx->id,
        'movement_type'  => MovementType::In->value,
        'item_id'        => $this->item->id,
        'location_id'    => $this->location->id,
        'quantity'       => 100,
        'total_cost'     => 155000,
    ]);
});

it('converts an enabled transaction currency and stores its functional snapshot', function (): void {
    $transactionCurrency = Currency::factory()->create();
    DB::table('organization_settings')
        ->where('organization_id', $this->organizationId)
        ->update([
            'enable_currencies' => json_encode([$this->currency->code, $transactionCurrency->code], JSON_THROW_ON_ERROR),
        ]);
    ExchangeRate::factory()->create([
        'organization_id'      => $this->organizationId,
        'source_currency_code' => $transactionCurrency->code,
        'target_currency_code' => $this->currency->code,
        'rate'                 => '2.5',
        'effective_at'         => now()->subMinute(),
    ]);
    setPermissionsTeamId($this->organizationId);

    $transaction = $this->service->recordTransaction([
        'reference_type'   => 'purchase_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'type'          => MovementType::In->value,
            'quantity'      => 10,
            'unit_code'     => $this->unit->code,
            'total_cost'    => '10.00',
            'currency_code' => $transactionCurrency->code,
        ]],
    ]);

    $movement = $transaction->movements->firstOrFail();
    $stock = $movement->stock;

    expect($stock->currency_code)->toBe($this->currency->code)
        ->and($stock->unit_cost)->toBe(250)
        ->and($movement->currency_code)->toBe($this->currency->code)
        ->and($movement->total_cost)->toBe(2500)
        ->and($stock->exchange_metadata)->toMatchArray([
            'currency_code'                  => $transactionCurrency->code,
            'functional_currency_code'       => $this->currency->code,
            'amount_in_transaction_currency' => '1000',
            'amount_in_functional_currency'  => '2500',
            'exchange_rate'                  => '2.500000000000',
        ])
        ->and($movement->exchange_metadata)->toEqual($stock->exchange_metadata);
});

it('derives unit cost and remainder from total cost', function (): void {
    $tx = $this->service->recordTransaction([
        'reference_type'   => 'purchase_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'type'          => MovementType::In->value,
            'quantity'      => 3,
            'unit_code'     => $this->unit->code,
            'total_cost'    => 100.01,
            'currency_code' => $this->currency->code,
        ]],
    ]);

    $stock = InventoryStock::query()->firstOrFail();
    $movement = $tx->movements->firstOrFail();

    expect($stock->unit_cost)->toBe(3333)
        ->and($stock->cost_remainder)->toBe(2)
        ->and($movement->total_cost)->toBe(10001);
});

it('loads only the requested transaction relations', function (): void {
    $payload = [
        'reference_type'   => 'purchase_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'type'          => MovementType::In->value,
            'quantity'      => 100,
            'unit_code'     => $this->unit->code,
            'total_cost'    => 1550.00,
            'currency_code' => $this->currency->code,
        ]],
    ];

    $transaction = $this->service->recordTransaction($payload, ['movements.stock']);

    expect($transaction->relationLoaded('movements'))->toBeTrue()
        ->and($transaction->movements->first()->relationLoaded('stock'))->toBeTrue();
});

it('processes an IN transaction with unit conversion', function (): void {
    $kgUnit = Unit::factory()->create(['ratio' => 1000, 'group_id' => $this->group->id]);

    $payload = [
        'reference_type'   => 'purchase_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'item_id'       => $this->item->id,
                'location_id'   => $this->location->id,
                'type'          => MovementType::In->value,
                'quantity'      => 2.5, // 2.5 kg
                'unit_code'     => $kgUnit->code,
                'total_cost'    => 37500.00,
                'currency_code' => $this->currency->code,
            ],
        ],
    ];

    $this->service->recordTransaction($payload);

    // Assert stock is created with base unit quantity (2.5 kg = 2500 base units)
    $stock = InventoryStock::first();
    expect($stock->quantity)->toBe(2500)
        ->and($stock->unit_code)->toBe($this->item->base_unit_code)
        ->and($stock->unit_cost)->toBe(1500);

    // Assert movement is recorded with base unit quantity
    $movement = InventoryMovement::first();
    expect($movement->quantity)->toBe(2500);
});

it('resolves inventory contracts passed in item_id and location_id before recording the transaction', function (): void {
    config()->set('inventory.enable_model_reference_preprocessing', true);

    $variant = $this->createTestMaterial();
    $company = $this->createTestWarehouse();

    $payload = [
        'reference_type'   => 'purchase_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'item'          => $variant,
                'location'      => $company,
                'type'          => MovementType::In->value,
                'quantity'      => 100,
                'unit_code'     => $this->unit->code,
                'total_cost'    => 1500.00,
                'currency_code' => $this->currency->code,
            ],
        ],
    ];

    $tx = $this->service->recordTransaction($payload);

    $item = InventoryItem::query()
        ->where('itemable_type', $variant->getMorphClass())
        ->where('itemable_id', $variant->getKey())
        ->firstOrFail();

    $location = InventoryLocation::query()
        ->where('external_type', $company->getMorphClass())
        ->where('external_id', $company->getKey())
        ->firstOrFail();

    expect($tx->movements)->toHaveCount(1)
        ->and($tx->movements->first()->item_id)->toBe($item->id)
        ->and($tx->movements->first()->location_id)->toBe($location->id);

    $this->assertDatabaseHas('inventory_stocks', [
        'item_id'       => $item->id,
        'location_id'   => $location->id,
        'quantity'      => 100,
        'remaining'     => 100,
        'currency_code' => $this->currency->code,
    ]);
});

it('fails an IN transaction if it contains an OUT movement', function (): void {
    $payload = [
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            ['type' => MovementType::In->value, 'item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 10, 'unit_code' => $this->unit->code, 'total_cost' => 100, 'currency_code' => $this->currency->code],
            ['type' => MovementType::Out->value, 'item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 5, 'unit_code' => $this->unit->code],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(ValidationException::class, "An 'IN' transaction can only contain 'in' movements.");

it('fails an IN transaction if total_cost or currency_code is missing', function ($fieldToRemove): void {
    $movement = [
        'item_id'       => $this->item->id,
        'location_id'   => $this->location->id,
        'type'          => MovementType::In->value,
        'quantity'      => 100,
        'unit_code'     => $this->unit->code,
        'total_cost'    => 1500,
        'currency_code' => $this->currency->code,
    ];
    unset($movement[$fieldToRemove]);

    $payload = [
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [$movement],
    ];

    $this->service->recordTransaction($payload);
})->with(['total_cost', 'currency_code'])->throws(ValidationException::class);

it('uses explicit stock metadata for the stock and movement metadata for the movement during an IN transaction', function (): void {
    $payload = [
        'reference_type'   => 'purchase_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'item_id'        => $this->item->id,
                'location_id'    => $this->location->id,
                'type'           => MovementType::In->value,
                'quantity'       => 100,
                'unit_code'      => $this->unit->code,
                'total_cost'     => 1500.00,
                'currency_code'  => $this->currency->code,
                'metadata'       => ['batch' => 'LOT-001', 'movement_note' => 'received'],
                'stock_metadata' => ['batch' => 'LOT-001'],
            ],
        ],
    ];

    $this->service->recordTransaction($payload);

    $stock = InventoryStock::firstOrFail();
    $movement = InventoryMovement::firstOrFail();

    expect($stock->metadata)->toBe(['batch' => 'LOT-001'])
        ->and($movement->metadata)->toBe(['batch' => 'LOT-001', 'movement_note' => 'received']);
});

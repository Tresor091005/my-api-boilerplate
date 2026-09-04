<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Enums\DeductionStrategy;
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
    currentTestCase()->configureInventoryCurrency($this->currency->code);
    $this->group = UnitGroup::factory()->create();
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);

    // Setup Inventory Data
    $this->item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $this->location = InventoryLocation::factory()->create();
});

it('rejects a transaction in a disabled currency', function (): void {
    $currency2 = Currency::factory()->create();
    $item2 = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);

    $payload = [
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            ['type' => 'in', 'item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 10, 'unit_code' => $this->unit->code, 'total_cost' => 10.00, 'currency_code' => $this->currency->code],
            ['type' => 'in', 'item_id' => $item2->id, 'location_id' => $this->location->id, 'quantity' => 5, 'unit_code' => $this->unit->code, 'total_cost' => 10.00, 'currency_code' => $currency2->code],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class);
});

it('maps validation error keys when recording a transaction', function (): void {
    $exception = null;

    try {
        $this->service->recordTransaction([
            'reference_type'   => 'test',
            'idempotency_key'  => fake()->uuid(),
            'reference_id'     => Str::uuid7()->toString(),
            'transaction_type' => TransactionType::In->value,
            'movements'        => [[
                'type'          => 'in',
                'item_id'       => Str::uuid7()->toString(),
                'location_id'   => $this->location->id,
                'quantity'      => 10,
                'unit_code'     => $this->unit->code,
                'total_cost'    => 10.00,
                'currency_code' => $this->currency->code,
            ]],
        ], errorKeyMap: [
            'movements.*.item_id' => 'lines.*.product_id',
        ]);
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(ValidationException::class)
        ->and($exception->errors())->toHaveKey('lines.0.product_id')
        ->and(array_key_exists('movements.0.item_id', $exception->errors()))->toBeFalse();
});

it('does not allow a transaction to reference another organization item', function (): void {
    $foreignItem = InventoryItem::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'base_unit_code'  => $this->unit->code,
    ]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $foreignItem->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $this->unit->code,
            'total_cost'    => 10.00,
            'currency_code' => $this->currency->code,
        ]],
    ]))->toThrow(ValidationException::class, 'The selected item is invalid or inactive.');
});

it('does not allow a transaction to reference another organization unit', function (): void {
    $foreignGroup = UnitGroup::factory()->create([
        'organization_id' => $this->otherOrganizationId,
    ]);
    $foreignUnit = Unit::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'group_id'        => $foreignGroup->id,
    ]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $foreignUnit->code,
            'total_cost'    => 10.00,
            'currency_code' => $this->currency->code,
        ]],
    ]))->toThrow(ValidationException::class, __('inventory::validation.unit_code_invalid'));
});

it('fails if unit does not belong to the same group as item base unit', function (): void {
    $otherGroup = UnitGroup::factory()->create();
    $otherUnit = Unit::factory()->create(['group_id' => $otherGroup->id]);

    $payload = [
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            ['type' => 'in', 'item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 10, 'unit_code' => $otherUnit->code, 'total_cost' => 10.00, 'currency_code' => $this->currency->code],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, "Unit {$otherUnit->code} belongs to a different group than item base unit {$this->item->base_unit_code}.");
});

it('rejects quantities that do not resolve to whole base units before any mutation', function (TransactionType $transactionType): void {
    $providedUnit = Unit::factory()->create([
        'ratio'    => 1_000,
        'group_id' => $this->group->id,
    ]);
    $otherLocation = InventoryLocation::factory()->create();
    $stock = InventoryStock::factory()
        ->for($this->item, 'item')
        ->for($this->location, 'location')
        ->create([
            'quantity'  => 10,
            'remaining' => 10,
        ]);
    $movement = [
        'item_id'     => $this->item->id,
        'location_id' => $this->location->id,
        'quantity'    => '0.0005',
        'unit_code'   => $providedUnit->code,
    ];

    if ($transactionType === TransactionType::In) {
        $movement += [
            'type'          => 'in',
            'total_cost'    => 10.00,
            'currency_code' => $this->currency->code,
        ];
    } elseif ($transactionType === TransactionType::Out) {
        $movement['type'] = 'out';
    } elseif ($transactionType === TransactionType::Transfer) {
        $movement['to_location_id'] = $otherLocation->id;
    }

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => $transactionType->value,
        'movements'        => [$movement],
    ]))->toThrow(ValidationException::class, 'Stock quantities must be whole base units.');

    expect($stock->refresh()->remaining)->toBe(10);
    $this->assertDatabaseCount('inventory_movements', 0);
})->with([
    'inbound'    => TransactionType::In,
    'outbound'   => TransactionType::Out,
    'adjustment' => TransactionType::Adjustment,
    'transfer'   => TransactionType::Transfer,
]);

it('rejects quantities exceeding the application maximum after base-unit conversion', function (): void {
    $providedUnit = Unit::factory()->create([
        'ratio'    => Unit::MAX_CUSTOM_RATIO,
        'group_id' => $this->group->id,
    ]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'quantity'      => (InventoryMovement::MAX_QUANTITY / Unit::MAX_CUSTOM_RATIO) + 1,
            'unit_code'     => $providedUnit->code,
            'total_cost'    => 10,
            'currency_code' => $this->currency->code,
        ]],
    ]))->toThrow(ValidationException::class, 'exceeds the maximum');

    $this->assertDatabaseCount('inventory_movements', 0);
    $this->assertDatabaseCount('inventory_stocks', 0);
});

it('fails if manual strategy is used without providing stock_ids', function (): void {
    $payload = [
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
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

it('fails if the same stock_id is selected more than once in one movement', function (): void {
    $stock = InventoryStock::factory()
        ->for($this->item, 'item')
        ->for($this->location, 'location')
        ->create();

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'type'        => 'out',
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'quantity'    => 10,
            'unit_code'   => $this->unit->code,
            'strategy'    => DeductionStrategy::Manual->value,
            'stock_ids'   => [$stock->id, $stock->id],
        ]],
    ]))->toThrow(ValidationException::class, __('inventory::validation.duplicate_stock_ids'));
});

it('fails if a provided stock_id does not belong to the correct item and location', function (): void {
    $otherItem = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $stockForOtherItem = InventoryStock::factory()->for($otherItem, 'item')->for($this->location, 'location')->create();

    $payload = [
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
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

it('ignores resolved inventory items with stock tracking disabled', function (): void {
    config()->set('inventory.enable_model_reference_preprocessing', true);

    $variant = $this->createTestMaterial();
    $activeVariant = $this->createTestMaterial();
    $company = $this->createTestWarehouse();

    InventoryItem::factory()->create([
        'itemable_type'          => $variant->getMorphClass(),
        'itemable_id'            => $variant->getKey(),
        'base_unit_code'         => $this->unit->code,
        'stock_tracking_enabled' => false,
    ]);

    $payload = [
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'type'          => 'in',
                'item'          => $variant,
                'location'      => $company,
                'quantity'      => 10,
                'unit_code'     => $this->unit->code,
                'total_cost'    => 10.00,
                'currency_code' => $this->currency->code,
            ],
            [
                'type'          => 'in',
                'item'          => $activeVariant,
                'location'      => $company,
                'quantity'      => 10,
                'unit_code'     => $this->unit->code,
                'total_cost'    => 10.00,
                'currency_code' => $this->currency->code,
            ],
        ],
    ];

    $transaction = $this->service->recordTransaction($payload);

    expect($transaction->movements)->toHaveCount(1)
        ->and($transaction->movements->first()->item->itemable_id)->toBe($activeVariant->getKey());
});

it('fails when a resolved inventory location is inactive', function (): void {
    config()->set('inventory.enable_model_reference_preprocessing', true);

    $variant = $this->createTestMaterial();
    $company = $this->createTestWarehouse();

    InventoryLocation::factory()->create([
        'external_type' => $company->getMorphClass(),
        'external_id'   => $company->getKey(),
        'is_active'     => false,
    ]);

    $payload = [
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'type'          => 'in',
                'item'          => $variant,
                'location'      => $company,
                'quantity'      => 10,
                'unit_code'     => $this->unit->code,
                'total_cost'    => 10.00,
                'currency_code' => $this->currency->code,
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, 'The selected location is invalid or inactive.');
});

it('does not persist resolved references when preprocessing is enabled but validation fails', function (): void {
    config()->set('inventory.enable_model_reference_preprocessing', true);

    $variant = $this->createTestMaterial();
    $company = $this->createTestWarehouse();

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'type'       => 'in',
                'item'       => $variant,
                'location'   => $company,
                'quantity'   => 10,
                'unit_code'  => $this->unit->code,
                'total_cost' => 10.00,
                // currency_code intentionally missing
            ],
        ],
    ]))->toThrow(ValidationException::class);

    expect(InventoryItem::query()
        ->where('itemable_type', $variant->getMorphClass())
        ->where('itemable_id', $variant->getKey())
        ->exists())->toBeFalse()
        ->and(InventoryLocation::query()
            ->where('external_type', $company->getMorphClass())
            ->where('external_id', $company->getKey())
            ->exists())->toBeFalse();
});

it('fails validation if Manual strategy remains item-resolved without stock_ids', function (): void {
    $this->item->update(['deduction_strategy' => DeductionStrategy::Manual]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'type'        => 'out',
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'quantity'    => 10,
            'unit_code'   => $this->unit->code,
        ]],
    ]))->toThrow(ValidationException::class, 'Stock IDs are required when strategy is manual.');
});

it('fails validation if Manual item strategy is missing stock_ids after config removal', function (): void {
    $this->item->update(['deduction_strategy' => DeductionStrategy::Manual]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'type'        => 'out',
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'quantity'    => 10,
            'unit_code'   => $this->unit->code,
        ]],
    ]))->toThrow(ValidationException::class, 'Stock IDs are required when strategy is manual.');
});

it('ignores movements for items with stock tracking disabled', function (): void {
    $this->item->update(['stock_tracking_enabled' => false]);
    $activeItem = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);

    $transaction = $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'type'          => 'in',
                'item_id'       => $this->item->id,
                'location_id'   => $this->location->id,
                'quantity'      => 10,
                'unit_code'     => $this->unit->code,
                'total_cost'    => 10.00,
                'currency_code' => $this->currency->code,
            ],
            [
                'type'          => 'in',
                'item_id'       => $activeItem->id,
                'location_id'   => $this->location->id,
                'quantity'      => 10,
                'unit_code'     => $this->unit->code,
                'total_cost'    => 10.00,
                'currency_code' => $this->currency->code,
            ],
        ],
    ]);

    expect($transaction->movements)->toHaveCount(1)
        ->and($transaction->movements->first()->item_id)->toBe($activeItem->id);
});

it('silently creates no movements when all items have stock tracking disabled', function (): void {
    $this->item->update(['stock_tracking_enabled' => false]);

    $transaction = $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $this->unit->code,
            'total_cost'    => 10.00,
            'currency_code' => $this->currency->code,
        ]],
    ]);

    expect($transaction->movements)->toBeEmpty();
});

it('fails transaction if the selected location is inactive', function (): void {
    $this->location->update(['is_active' => false]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $this->unit->code,
            'total_cost'    => 10.00,
            'currency_code' => $this->currency->code,
        ]],
    ]))->toThrow(ValidationException::class, 'The selected location is invalid or inactive.');
});

it('fails if the item base unit has a ratio different than 1', function (): void {
    $invalidBaseUnit = Unit::factory()->create(['ratio' => 2, 'group_id' => $this->group->id]);
    $item = InventoryItem::factory()->create(['base_unit_code' => $invalidBaseUnit->code]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $item->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $invalidBaseUnit->code,
            'total_cost'    => 10.00,
            'currency_code' => $this->currency->code,
        ]],
    ]))->toThrow(\Exception::class, 'must have a ratio of 1');
});

it('fails if total_cost has more decimal places than allowed by the currency', function (): void {
    $currency = Currency::factory()->create(['precision' => 2]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $this->unit->code,
            'total_cost'    => '10.123',
            'currency_code' => $currency->code,
        ]],
    ]))->toThrow(ValidationException::class, "must have at most {$currency->precision} decimal places");
});

it('fails if total_cost is negative', function (): void {
    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $this->unit->code,
            'total_cost'    => -1,
            'currency_code' => $this->currency->code,
        ]],
    ]))->toThrow(ValidationException::class);
});

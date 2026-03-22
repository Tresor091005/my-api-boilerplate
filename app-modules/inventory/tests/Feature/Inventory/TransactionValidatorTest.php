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
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Services\InventoryService;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(InventoryService::class);
    $this->unitCache = app(UnitCache::class);

    // Setup Master Data
    $this->currency = Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€', 'precision' => 2]);
    $group = UnitGroup::firstOrCreate(['name' => 'Weight'], ['is_builtin' => false]);
    $this->unitCode = 'u-'.Str::random(5);
    $this->unit = Unit::create(['code' => $this->unitCode, 'name' => 'Gram', 'ratio' => 1, 'group_id' => $group->id]);

    $this->unitCache->rewarmUnits();
    $this->unitCache->rewarmCurrencies();

    // Setup Inventory Data
    $this->item = InventoryItem::create([
        'itemable_type'  => 'product',
        'itemable_id'    => Str::uuid7()->toString(),
        'sku'            => 'TEST-ITEM-'.Str::random(5),
        'base_unit_code' => $this->unitCode,
        'is_active'      => true,
    ]);

    $this->location = InventoryLocation::create([
        'external_type' => 'warehouse',
        'external_id'   => Str::uuid7()->toString(),
        'is_active'     => true,
    ]);
});

it('validates rule 1: unique item_id + location_id in movements', function (): void {
    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'item_id'       => $this->item->id,
                'location_id'   => $this->location->id,
                'type'          => MovementType::In->value,
                'quantity'      => 10,
                'unit_code'     => $this->unitCode,
                'unit_cost'     => 100,
                'currency_code' => 'EUR',
            ],
            [
                'item_id'       => $this->item->id,
                'location_id'   => $this->location->id, // Duplicate
                'type'          => MovementType::In->value,
                'quantity'      => 20,
                'unit_code'     => $this->unitCode,
                'unit_cost'     => 100,
                'currency_code' => 'EUR',
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, 'The same item cannot appear multiple times for the same location in a single transaction.');
});

it('validates rule 2: single currency across all in movements', function (): void {
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'Dollar', 'symbol' => '$', 'precision' => 2]);
    $this->unitCache->rewarmCurrencies();

    $item2 = InventoryItem::create([
        'itemable_type'  => 'product',
        'itemable_id'    => Str::uuid7()->toString(),
        'sku'            => 'TEST-ITEM-2',
        'base_unit_code' => $this->unitCode,
        'is_active'      => true,
    ]);

    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'item_id'       => $this->item->id,
                'location_id'   => $this->location->id,
                'type'          => MovementType::In->value,
                'quantity'      => 10,
                'unit_code'     => $this->unitCode,
                'unit_cost'     => 100,
                'currency_code' => 'EUR',
            ],
            [
                'item_id'       => $item2->id,
                'location_id'   => $this->location->id,
                'type'          => MovementType::In->value,
                'quantity'      => 20,
                'unit_code'     => $this->unitCode,
                'unit_cost'     => 100,
                'currency_code' => 'USD', // Different currency
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, 'All "in" movements in a transaction must use the same currency code.');
});

it('validates rule 7: unit_cost and currency_code required for in movements', function (): void {
    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'item_id'       => $this->item->id,
                'location_id'   => $this->location->id,
                'type'          => MovementType::In->value,
                'quantity'      => 10,
                'unit_code'     => $this->unitCode,
                'currency_code' => '', // Empty currency code
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class);
});

it('validates rule 8: stock IDs belong to correct item and location', function (): void {
    $stock = InventoryStock::create([
        'item_id'       => $this->item->id,
        'location_id'   => $this->location->id,
        'unit_cost'     => 100,
        'currency_code' => 'EUR',
        'quantity'      => 100,
        'remaining'     => 100,
        'unit_code'     => $this->unitCode,
    ]);

    $otherLocation = InventoryLocation::create([
        'external_type' => 'warehouse',
        'external_id'   => Str::uuid7()->toString(),
        'is_active'     => true,
    ]);

    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $otherLocation->id, // Incorrect location for this stock
                'type'        => MovementType::Out->value,
                'quantity'    => 10,
                'unit_code'   => $this->unitCode,
                'strategy'    => DeductionStrategy::Manual->value,
                'stock_ids'   => [$stock->id],
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, "Stock ID {$stock->id} does not belong to the correct item and location.");
});

it('validates rule 9: stock_ids required when strategy is manual', function (): void {
    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->location->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 10,
                'unit_code'   => $this->unitCode,
                'strategy'    => DeductionStrategy::Manual->value,
                // stock_ids missing
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, 'Stock IDs are required when strategy is manual.');
});

it('allows metadata in movements and saves it', function (): void {
    $metadata = ['batch_no' => 'B123', 'color' => 'blue'];

    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'item_id'       => $this->item->id,
                'location_id'   => $this->location->id,
                'type'          => MovementType::In->value,
                'quantity'      => 10,
                'unit_code'     => $this->unitCode,
                'unit_cost'     => 100,
                'currency_code' => 'EUR',
                'metadata'      => $metadata,
            ],
        ],
    ];

    $tx = $this->service->recordTransaction($payload);

    $movement = $tx->movements->first();
    expect($movement->metadata)->toEqual($metadata);

    $stock = InventoryStock::where('id', $movement->stock_id)->first();
    expect($stock->metadata)->toEqual($metadata);
});

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
    $this->group = UnitGroup::factory()->create(['is_builtin' => false]);
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);

    // Setup Inventory Data
    $this->item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $this->location = InventoryLocation::factory()->create();
});

it('successfully processes a simple IN transaction', function (): void {
    $payload = [
        'reference_type'   => 'purchase_order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'item_id'       => $this->item->id,
                'location_id'   => $this->location->id,
                'type'          => MovementType::In->value,
                'quantity'      => 100,
                'unit_code'     => $this->unit->code,
                'unit_cost'     => 1500, // 15.00
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
        'unit_cost'     => 150000,
        'currency_code' => $this->currency->code,
    ]);

    // 3. Assert Movement is created
    $this->assertDatabaseHas('inventory_movements', [
        'transaction_id' => $tx->id,
        'movement_type'  => MovementType::In->value,
        'item_id'        => $this->item->id,
        'location_id'    => $this->location->id,
        'quantity'       => 100,
        'unit_cost'      => 150000,
    ]);
});

it('processes an IN transaction with unit conversion', function (): void {
    $kgUnit = Unit::factory()->create(['ratio' => 1000, 'group_id' => $this->group->id]);

    $payload = [
        'reference_type'   => 'purchase_order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'item_id'       => $this->item->id,
                'location_id'   => $this->location->id,
                'type'          => MovementType::In->value,
                'quantity'      => 2.5, // 2.5 kg
                'unit_code'     => $kgUnit->code,
                'unit_cost'     => 1500,
                'currency_code' => $this->currency->code,
            ],
        ],
    ];

    $this->service->recordTransaction($payload);

    // Assert stock is created with base unit quantity (2.5 kg = 2500 base units)
    $stock = InventoryStock::first();
    expect($stock->quantity)->toBe(2500)
        ->and($stock->unit_code)->toBe($this->item->base_unit_code);

    // Assert movement is recorded with base unit quantity
    $movement = InventoryMovement::first();
    expect($movement->quantity)->toBe(2500);
});

it('fails an IN transaction if it contains an OUT movement', function (): void {
    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            ['type' => MovementType::In->value, 'item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 10, 'unit_code' => $this->unit->code, 'unit_cost' => 10, 'currency_code' => $this->currency->code],
            ['type' => MovementType::Out->value, 'item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 5, 'unit_code' => $this->unit->code],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(ValidationException::class, "An 'IN' transaction can only contain 'in' movements.");

it('fails an IN transaction if unit_cost or currency_code is missing', function ($fieldToRemove): void {
    $movement = [
        'item_id'       => $this->item->id,
        'location_id'   => $this->location->id,
        'type'          => MovementType::In->value,
        'quantity'      => 100,
        'unit_code'     => $this->unit->code,
        'unit_cost'     => 1500,
        'currency_code' => $this->currency->code,
    ];
    unset($movement[$fieldToRemove]);

    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [$movement],
    ];

    $this->service->recordTransaction($payload);
})->with(['unit_cost', 'currency_code'])->throws(ValidationException::class);

todo('correctly saves different metadata on Movement and Stock during an IN transaction');

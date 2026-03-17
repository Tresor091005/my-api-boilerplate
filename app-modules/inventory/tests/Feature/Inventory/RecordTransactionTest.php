<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Exceptions\TransferBalanceException;
use Lahatre\Inventory\Exceptions\UnitGroupMismatchException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Services\InventoryService;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(InventoryService::class);

    // Setup Master Data
    $this->currency = Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€', 'precision' => 2]);
    $group = UnitGroup::firstOrCreate(['name' => 'Weight'], ['is_builtin' => false]);
    $this->baseUnit = Unit::firstOrCreate(['code' => 'test-g'], ['name' => 'Test Gram', 'ratio' => 1, 'group_id' => $group->id]);
    $this->kgUnit = Unit::firstOrCreate(['code' => 'test-kg'], ['name' => 'Test Kilogram', 'ratio' => 1000, 'group_id' => $group->id]);

    // Setup Inventory Data
    $this->item = InventoryItem::create([
        'itemable_type'  => 'product',
        'itemable_id'    => Str::uuid7()->toString(),
        'sku'            => 'TEST-SKU',
        'base_unit_code' => 'test-g',
        'is_active'      => true,
    ]);

    $this->locA = InventoryLocation::create(['external_type' => 'warehouse', 'external_id' => Str::uuid7()->toString(), 'is_active' => true]);
    $this->locB = InventoryLocation::create(['external_type' => 'warehouse', 'external_id' => Str::uuid7()->toString(), 'is_active' => true]);
});

it('processes a complex transfer with FIFO split and unit conversion', function (): void {
    // 1. Initial State: Create two lots at Location A
    // Lot 1: 500g created at T-2
    $lot1 = new InventoryStock([
        'item_id'       => $this->item->id,
        'location_id'   => $this->locA->id,
        'unit_cost'     => 1000, // 10.00
        'currency_code' => 'EUR',
        'quantity'      => 500,
        'remaining'     => 500,
        'unit_code'     => 'test-g',
    ]);
    $lot1->forceFill(['created_at' => now()->subMinutes(10)])->save();

    // Lot 2: 500g created at T-1
    $lot2 = new InventoryStock([
        'item_id'       => $this->item->id,
        'location_id'   => $this->locA->id,
        'unit_cost'     => 1200, // 12.00
        'currency_code' => 'EUR',
        'quantity'      => 500,
        'remaining'     => 500,
        'unit_code'     => 'test-g',
    ]);
    $lot2->forceFill(['created_at' => now()->subMinutes(5)])->save();

    // Total stock at A = 1000g.
    // We want to transfer 0.75 kg (750g) from A to B.
    // FIFO should take: 500g from Lot 1 (full) + 250g from Lot 2 (partial).

    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Transfer,
        'movements'        => [
            [
                'item_id'       => $this->item->id,
                'location_id'   => $this->locA->id,
                'type'          => 'out',
                'quantity'      => 0.75, // in KG
                'unit_code'     => 'test-kg',
                'unit_cost'     => 0, // Out cost is determined by FIFO
                'currency_code' => 'EUR',
            ],
            [
                'item_id'       => $this->item->id,
                'location_id'   => $this->locB->id,
                'type'          => 'in',
                'quantity'      => 750, // in Grams
                'unit_code'     => 'test-g',
                'unit_cost'     => 1100, // Weighted average or new cost
                'currency_code' => 'EUR',
            ],
        ],
    ];

    $tx = $this->service->recordTransaction($payload);

    // VERIFICATIONS

    // 1. Transaction Table
    expect($tx->transaction_type)->toBe(TransactionType::Transfer);
    expect(InventoryTransaction::count())->toBe(1);

    // 2. Movements Table (Should have 3 movements due to FIFO split)
    // - 500g OUT (Lot 1)
    // - 250g OUT (Lot 2)
    // - 750g IN (New Lot at B)
    $movements = InventoryMovement::orderBy('created_at')->get();
    expect($movements)->toHaveCount(3);

    $out1 = $movements->where('movement_type', MovementType::Out)->firstWhere('quantity', 500);
    $out2 = $movements->where('movement_type', MovementType::Out)->firstWhere('quantity', 250);
    $in = $movements->firstWhere('movement_type', MovementType::In);

    expect($out1->location_id)->toBe($this->locA->id);
    expect($out2->location_id)->toBe($this->locA->id);
    expect($in->location_id)->toBe($this->locB->id);
    expect($in->quantity)->toBe(750);

    // 3. Stocks Table
    $stocksA = InventoryStock::where('location_id', $this->locA->id)->orderBy('created_at')->get();
    expect($stocksA[0]->remaining)->toBe(0);   // Lot 1 exhausted
    expect($stocksA[1]->remaining)->toBe(250); // Lot 2 partial

    $stockB = InventoryStock::where('location_id', $this->locB->id)->first();
    expect($stockB->quantity)->toBe(750);
    expect($stockB->remaining)->toBe(750);
});

it('fails if transfer is not balanced', function (): void {
    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Transfer,
        'movements'        => [
            ['item_id' => $this->item->id, 'location_id' => $this->locA->id, 'type' => 'out', 'quantity' => 10, 'unit_code' => 'test-g', 'unit_cost' => 0, 'currency_code' => 'EUR'],
            ['item_id' => $this->item->id, 'location_id' => $this->locB->id, 'type' => 'in', 'quantity' => 5, 'unit_code' => 'test-g', 'unit_cost' => 0, 'currency_code' => 'EUR'],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(TransferBalanceException::class);

it('fails if stock is insufficient', function (): void {
    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out,
        'movements'        => [
            ['item_id' => $this->item->id, 'location_id' => $this->locA->id, 'type' => 'out', 'quantity' => 100, 'unit_code' => 'test-g', 'unit_cost' => 0, 'currency_code' => 'EUR'],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(InsufficientStockException::class);

it('fails if unit group does not match', function (): void {
    $groupVolume = UnitGroup::create(['name' => 'Volume', 'is_builtin' => false]);
    $liter = Unit::create(['code' => 'L', 'name' => 'Liter', 'ratio' => 1, 'group_id' => $groupVolume->id]);

    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In,
        'movements'        => [
            ['item_id' => $this->item->id, 'location_id' => $this->locA->id, 'type' => 'in', 'quantity' => 1, 'unit_code' => 'L', 'unit_cost' => 10, 'currency_code' => 'EUR'],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(UnitGroupMismatchException::class);

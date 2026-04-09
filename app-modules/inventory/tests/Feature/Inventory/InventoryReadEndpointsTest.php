<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->inventoryService = app(InventoryService::class);
    $this->currency = Currency::factory()->create();
    $this->group = UnitGroup::factory()->create();
    $this->unit = Unit::factory()->create([
        'ratio'    => 1,
        'group_id' => $this->group->id,
    ]);
});

it('returns aggregated item stock, location stock, and active lots', function (): void {
    $item = InventoryItem::factory()->create([
        'base_unit_code'     => $this->unit->code,
        'deduction_strategy' => DeductionStrategy::Fefo,
    ]);
    $locationA = InventoryLocation::factory()->create();
    $locationB = InventoryLocation::factory()->create();

    $lot1 = InventoryStock::factory()->for($item, 'item')->for($locationA, 'location')->create([
        'unit_code'       => $this->unit->code,
        'currency_code'   => $this->currency->code,
        'quantity'        => 120,
        'remaining'       => 120,
        'expiration_date' => now()->addDays(10),
        'created_at'      => now()->subDays(2),
        'metadata'        => ['lot_number' => 'LOT-A1'],
    ]);
    $lot2 = InventoryStock::factory()->for($item, 'item')->for($locationA, 'location')->create([
        'unit_code'       => $this->unit->code,
        'currency_code'   => $this->currency->code,
        'quantity'        => 80,
        'remaining'       => 80,
        'expiration_date' => now()->addDays(5),
        'created_at'      => now()->subDay(),
        'metadata'        => ['lot_number' => 'LOT-A2'],
    ]);
    InventoryStock::factory()->for($item, 'item')->for($locationB, 'location')->create([
        'unit_code'     => $this->unit->code,
        'currency_code' => $this->currency->code,
        'quantity'      => 40,
        'remaining'     => 40,
    ]);

    $this->getJson("/v1/inventory/items/{$item->id}/stock")
        ->assertOk()
        ->assertJsonPath('item_id', $item->id)
        ->assertJsonPath('total_remaining', 240)
        ->assertJsonPath('unit_code', $this->unit->code)
        ->assertJsonCount(2, 'locations')
        ->assertJsonFragment([
            'location_id' => $locationA->id,
            'remaining'   => 200,
        ])
        ->assertJsonFragment([
            'location_id' => $locationB->id,
            'remaining'   => 40,
        ]);

    $this->getJson("/v1/inventory/locations/{$locationA->id}/stock")
        ->assertOk()
        ->assertJsonPath('location_id', $locationA->id)
        ->assertJsonPath('items.0.item_id', $item->id)
        ->assertJsonPath('items.0.remaining', 200)
        ->assertJsonPath('items.0.unit_code', $this->unit->code);

    $this->getJson("/v1/inventory/items/{$item->id}/locations/{$locationA->id}/lots")
        ->assertOk()
        ->assertJsonPath('item_id', $item->id)
        ->assertJsonPath('location_id', $locationA->id)
        ->assertJsonPath('deduction_strategy', DeductionStrategy::Fefo->value)
        ->assertJsonPath('total_remaining', 200)
        ->assertJsonPath('lots.0.stock_id', $lot2->id)
        ->assertJsonPath('lots.1.stock_id', $lot1->id);

    $this->getJson("/v1/inventory/items/{$item->id}/locations/{$locationA->id}/lots?strategy=fifo")
        ->assertOk()
        ->assertJsonPath('deduction_strategy', DeductionStrategy::Fifo->value)
        ->assertJsonPath('lots.0.stock_id', $lot1->id)
        ->assertJsonPath('lots.1.stock_id', $lot2->id);
});

it('returns stock summary and expiring lots with pagination metadata', function (): void {
    $itemA = InventoryItem::factory()->create([
        'base_unit_code' => $this->unit->code,
        'sku'            => 'ITEM-A',
    ]);
    $itemB = InventoryItem::factory()->create([
        'base_unit_code' => $this->unit->code,
        'sku'            => 'ITEM-B',
    ]);
    $locationA = InventoryLocation::factory()->create();
    $locationB = InventoryLocation::factory()->create();

    $expiringLot = InventoryStock::factory()->for($itemA, 'item')->for($locationA, 'location')->create([
        'unit_code'       => $this->unit->code,
        'currency_code'   => $this->currency->code,
        'quantity'        => 70,
        'remaining'       => 70,
        'expiration_date' => now()->addDays(4),
    ]);

    InventoryStock::factory()->for($itemB, 'item')->for($locationA, 'location')->create([
        'unit_code'       => $this->unit->code,
        'currency_code'   => $this->currency->code,
        'quantity'        => 90,
        'remaining'       => 90,
        'expiration_date' => now()->addDays(12),
    ]);

    InventoryStock::factory()->for($itemA, 'item')->for($locationB, 'location')->create([
        'unit_code'     => $this->unit->code,
        'currency_code' => $this->currency->code,
        'quantity'      => 30,
        'remaining'     => 30,
    ]);

    $this->getJson("/v1/inventory/stock/summary?per_page=1&page=1&location_id[]={$locationA->id}")
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(1, 'data');

    $this->getJson('/v1/inventory/stock/expiring?days=7')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.stock_id', $expiringLot->id)
        ->assertJsonPath('data.0.item_id', $itemA->id)
        ->assertJsonPath('data.0.location_id', $locationA->id)
        ->assertJsonPath('data.0.remaining', 70);
});

it('validates query filters on expiring stock endpoint', function (): void {
    $this->getJson('/v1/inventory/stock/expiring?days=0')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('days');
});

it('returns movements filtered by item, location, and transaction reference plus transaction details', function (): void {
    $item = InventoryItem::factory()->create([
        'base_unit_code' => $this->unit->code,
    ]);
    $location = InventoryLocation::factory()->create();

    $purchaseReferenceId = Str::uuid7()->toString();
    $saleReferenceId = Str::uuid7()->toString();

    $inTransaction = $this->inventoryService->recordTransaction([
        'reference_type'   => 'purchase_order',
        'reference_id'     => $purchaseReferenceId,
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'item_id'       => $item->id,
            'location_id'   => $location->id,
            'type'          => MovementType::In->value,
            'quantity'      => 100,
            'unit_code'     => $this->unit->code,
            'unit_cost'     => 450,
            'currency_code' => $this->currency->code,
            'metadata'      => ['batch' => 'IN-001'],
        ]],
    ]);

    $outTransaction = $this->inventoryService->recordTransaction([
        'reference_type'   => 'sale_order',
        'reference_id'     => $saleReferenceId,
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $item->id,
            'location_id' => $location->id,
            'type'        => MovementType::Out->value,
            'quantity'    => 35,
            'unit_code'   => $this->unit->code,
        ]],
    ]);

    $this->getJson("/v1/inventory/items/{$item->id}/movements?movement_type=out&reference_type=sale_order&reference_id={$saleReferenceId}")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.transaction_id', $outTransaction->id)
        ->assertJsonPath('data.0.movement_type', MovementType::Out->value)
        ->assertJsonPath('data.0.quantity', 35);

    $this->getJson("/v1/inventory/locations/{$location->id}/movements")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonCount(2, 'data');

    $this->getJson("/v1/inventory/transactions/{$inTransaction->id}")
        ->assertOk()
        ->assertJsonPath('id', $inTransaction->id)
        ->assertJsonPath('transaction_type', TransactionType::In->value)
        ->assertJsonPath('movements.0.transaction_id', $inTransaction->id)
        ->assertJsonPath('movements.0.metadata.batch', 'IN-001');
});

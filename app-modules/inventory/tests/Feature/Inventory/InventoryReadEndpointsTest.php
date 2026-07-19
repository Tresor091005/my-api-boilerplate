<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lahatre\Iam\Http\Middleware\ResolveAuthContext;
use Lahatre\Iam\Http\Middleware\SetTeamPermissionsId;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
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
    $this->withoutMiddleware([
        Authenticate::class,
        ResolveAuthContext::class,
        SetTeamPermissionsId::class,
    ]);
    $this->ensureInventoryTestTables();
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
        'is_expirable'       => true,
    ]);
    $locationA = InventoryLocation::factory()->create();
    $locationB = InventoryLocation::factory()->create();

    $lot1 = InventoryStock::factory()->for($item, 'item')->for($locationA, 'location')->create([
        'unit_code'       => $this->unit->code,
        'currency_code'   => $this->currency->code,
        'unit_cost'       => 1500,
        'quantity'        => 120,
        'remaining'       => 120,
        'expiration_date' => now()->addDays(10),
        'created_at'      => now()->subDays(2),
        'metadata'        => ['lot_number' => 'LOT-A1'],
    ]);
    $lot2 = InventoryStock::factory()->for($item, 'item')->for($locationA, 'location')->create([
        'unit_code'       => $this->unit->code,
        'currency_code'   => $this->currency->code,
        'unit_cost'       => 2500,
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

    $this->getJson("/v1/inventory/items/{$item->id}/value")
        ->assertOk()
        ->assertJsonPath('item_id', $item->id)
        ->assertJsonStructure([
            'totals'    => [['currency_code', 'total_value']],
            'locations' => [['location_id', 'values' => [['currency_code', 'total_value']]]],
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
        ->assertJsonPath('lots.0.unit_cost', '25.00')
        ->assertJsonPath('lots.1.stock_id', $lot1->id);

    $this->getJson("/v1/inventory/items/{$item->id}/locations/{$locationA->id}/lots?strategy=fifo")
        ->assertOk()
        ->assertJsonPath('deduction_strategy', DeductionStrategy::Fifo->value)
        ->assertJsonPath('lots.0.stock_id', $lot1->id)
        ->assertJsonPath('lots.1.stock_id', $lot2->id);
});

it('returns item value aggregated by location and currency and supports filters', function (): void {
    $currencyA = Currency::factory()->create();
    $currencyB = Currency::factory()->create();

    $item = InventoryItem::factory()->create([
        'base_unit_code' => $this->unit->code,
    ]);
    $locationA = InventoryLocation::factory()->create();
    $locationB = InventoryLocation::factory()->create();

    InventoryStock::factory()->for($item, 'item')->for($locationA, 'location')->create([
        'unit_code'     => $this->unit->code,
        'currency_code' => $currencyA->code,
        'unit_cost'     => 100,
        'quantity'      => 10,
        'remaining'     => 10,
    ]);
    InventoryStock::factory()->for($item, 'item')->for($locationA, 'location')->create([
        'unit_code'     => $this->unit->code,
        'currency_code' => $currencyA->code,
        'unit_cost'     => 200,
        'quantity'      => 5,
        'remaining'     => 5,
    ]);
    InventoryStock::factory()->for($item, 'item')->for($locationA, 'location')->create([
        'unit_code'     => $this->unit->code,
        'currency_code' => $currencyB->code,
        'unit_cost'     => 300,
        'quantity'      => 2,
        'remaining'     => 2,
    ]);
    InventoryStock::factory()->for($item, 'item')->for($locationB, 'location')->create([
        'unit_code'     => $this->unit->code,
        'currency_code' => $currencyA->code,
        'unit_cost'     => 400,
        'quantity'      => 1,
        'remaining'     => 1,
    ]);

    // Totals:
    // locationA/currencyA = 10*100 + 5*200 = 2000
    // locationA/currencyB = 2*300 = 600
    // locationB/currencyA = 1*400 = 400
    $this->getJson("/v1/inventory/items/{$item->id}/value")
        ->assertOk()
        ->assertJsonPath('item_id', $item->id)
        ->assertJsonFragment(['currency_code' => $currencyA->code, 'total_value' => '24.00'])
        ->assertJsonFragment(['currency_code' => $currencyB->code, 'total_value' => '6.00'])
        ->assertJsonFragment([
            'location_id' => $locationA->id,
            'values'      => [
                ['currency_code' => $currencyA->code, 'total_value' => '20.00'],
                ['currency_code' => $currencyB->code, 'total_value' => '6.00'],
            ],
        ])
        ->assertJsonFragment([
            'location_id' => $locationB->id,
            'values'      => [
                ['currency_code' => $currencyA->code, 'total_value' => '4.00'],
            ],
        ]);

    $this->getJson("/v1/inventory/items/{$item->id}/value?location_id[]={$locationA->id}")
        ->assertOk()
        ->assertJsonCount(1, 'locations')
        ->assertJsonPath('locations.0.location_id', $locationA->id);

    $this->getJson("/v1/inventory/items/{$item->id}/value?currency_code[]={$currencyB->code}")
        ->assertOk()
        ->assertJsonCount(1, 'totals')
        ->assertJsonFragment(['currency_code' => $currencyB->code, 'total_value' => '6.00']);
});

it('returns location value aggregated by item and currency and supports filters', function (): void {
    $currencyA = Currency::factory()->create();
    $currencyB = Currency::factory()->create();

    $itemA = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $itemB = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $location = InventoryLocation::factory()->create();

    InventoryStock::factory()->for($itemA, 'item')->for($location, 'location')->create([
        'unit_code'     => $this->unit->code,
        'currency_code' => $currencyA->code,
        'unit_cost'     => 100,
        'quantity'      => 10,
        'remaining'     => 10,
    ]);
    InventoryStock::factory()->for($itemA, 'item')->for($location, 'location')->create([
        'unit_code'     => $this->unit->code,
        'currency_code' => $currencyB->code,
        'unit_cost'     => 300,
        'quantity'      => 2,
        'remaining'     => 2,
    ]);
    InventoryStock::factory()->for($itemB, 'item')->for($location, 'location')->create([
        'unit_code'     => $this->unit->code,
        'currency_code' => $currencyA->code,
        'unit_cost'     => 400,
        'quantity'      => 1,
        'remaining'     => 1,
    ]);

    // Totals:
    // itemA/currencyA = 10*100 = 1000
    // itemA/currencyB = 2*300 = 600
    // itemB/currencyA = 1*400 = 400
    // totals currencyA = 1400, currencyB = 600
    $this->getJson("/v1/inventory/locations/{$location->id}/value")
        ->assertOk()
        ->assertJsonPath('location_id', $location->id)
        ->assertJsonFragment(['currency_code' => $currencyA->code, 'total_value' => '14.00'])
        ->assertJsonFragment(['currency_code' => $currencyB->code, 'total_value' => '6.00'])
        ->assertJsonFragment([
            'item_id' => $itemA->id,
            'values'  => [
                ['currency_code' => $currencyA->code, 'total_value' => '10.00'],
                ['currency_code' => $currencyB->code, 'total_value' => '6.00'],
            ],
        ])
        ->assertJsonFragment([
            'item_id' => $itemB->id,
            'values'  => [
                ['currency_code' => $currencyA->code, 'total_value' => '4.00'],
            ],
        ]);

    $this->getJson("/v1/inventory/locations/{$location->id}/value?item_id[]={$itemA->id}")
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.item_id', $itemA->id);

    $this->getJson("/v1/inventory/locations/{$location->id}/value?currency_code[]={$currencyB->code}")
        ->assertOk()
        ->assertJsonCount(1, 'totals')
        ->assertJsonFragment(['currency_code' => $currencyB->code, 'total_value' => '6.00']);
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

    $this->getJson("/v1/inventory/stock/summary?per_page=1&location_id[]={$locationA->id}")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']])
        ->assertJsonCount(1, 'data');

    $this->getJson('/v1/inventory/stock/expiring?days=7')
        ->assertOk()
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']])
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

it('lists inventory items and locations with optional includes', function (): void {
    $variant = $this->createTestMaterial();
    $item = InventoryItem::factory()->create([
        'itemable_type'  => $variant->getMorphClass(),
        'itemable_id'    => $variant->id,
        'sku'            => $variant->sku,
        'base_unit_code' => $this->unit->code,
    ]);

    $org = $this->createTestWarehouse();
    $location = InventoryLocation::factory()->create([
        'external_type' => $org->getMorphClass(),
        'external_id'   => $org->id,
    ]);

    $this->getJson("/v1/inventory/items/{$item->id}")
        ->assertOk()
        ->assertJsonPath('id', $item->id)
        ->assertJsonPath('is_expirable', false)
        ->assertJsonMissingPath('itemable');

    $this->getJson("/v1/inventory/items/{$item->id}?include=itemable")
        ->assertOk()
        ->assertJsonPath('id', $item->id)
        ->assertJsonPath('itemable.id', $variant->id)
        ->assertJsonPath('itemable.sku', $variant->sku);

    $this->getJson("/v1/inventory/items?ids[]={$item->id}&include=itemable")
        ->assertOk()
        ->assertJsonPath('data.0.id', $item->id)
        ->assertJsonPath('data.0.itemable.id', $variant->id)
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']]);

    $this->getJson("/v1/inventory/locations/{$location->id}")
        ->assertOk()
        ->assertJsonPath('id', $location->id)
        ->assertJsonMissingPath('external');

    $this->getJson("/v1/inventory/locations/{$location->id}?include=external")
        ->assertOk()
        ->assertJsonPath('id', $location->id)
        ->assertJsonPath('external.id', $org->id)
        ->assertJsonPath('external.name', $org->name);

    $this->getJson("/v1/inventory/locations?ids[]={$location->id}&include=external")
        ->assertOk()
        ->assertJsonPath('data.0.id', $location->id)
        ->assertJsonPath('data.0.external.id', $org->id)
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']]);

    $this->getJson('/v1/inventory/items')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['per_page', 'next_cursor', 'prev_cursor']]);

    $this->getJson("/v1/inventory/items?itemable_type={$item->itemable_type}&itemable_id[]={$item->itemable_id}&include=itemable")
        ->assertOk()
        ->assertJsonPath('data.0.id', $item->id)
        ->assertJsonPath('data.0.itemable.id', $variant->id);

    $this->getJson("/v1/inventory/locations?external_type={$location->external_type}&external_id[]={$location->external_id}&include=external")
        ->assertOk()
        ->assertJsonPath('data.0.id', $location->id)
        ->assertJsonPath('data.0.external.id', $org->id);
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
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => $purchaseReferenceId,
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'item_id'       => $item->id,
            'location_id'   => $location->id,
            'type'          => MovementType::In->value,
            'quantity'      => 100,
            'unit_code'     => $this->unit->code,
            'total_cost'    => 450.00,
            'currency_code' => $this->currency->code,
            'metadata'      => ['batch' => 'IN-001'],
        ]],
    ]);

    $outTransaction = $this->inventoryService->recordTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
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
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.transaction_id', $outTransaction->id)
        ->assertJsonPath('data.0.movement_type', MovementType::Out->value)
        ->assertJsonPath('data.0.quantity', 35);

    $this->getJson("/v1/inventory/locations/{$location->id}/movements")
        ->assertOk()
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']])
        ->assertJsonCount(2, 'data');

    $this->getJson("/v1/inventory/transactions/{$inTransaction->id}")
        ->assertOk()
        ->assertJsonPath('id', $inTransaction->id)
        ->assertJsonPath('transaction_type', TransactionType::In->value)
        ->assertJsonPath('movements.0.transaction_id', $inTransaction->id)
        ->assertJsonPath('movements.0.total_cost', '450.00')
        ->assertJsonPath('movements.0.stock.unit_cost', '4.50')
        ->assertJsonPath('movements.0.metadata.batch', 'IN-001');

    $this->getJson("/v1/inventory/transactions?per_page=1&reference_type=purchase_order&reference_id[]={$purchaseReferenceId}")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']])
        ->assertJsonPath('data.0.id', $inTransaction->id)
        ->assertJsonPath('data.0.reference_type', 'purchase_order')
        ->assertJsonPath('data.0.reference_id', $purchaseReferenceId);

    $this->getJson("/v1/inventory/transactions?ids[]={$outTransaction->id}")
        ->assertOk()
        ->assertJsonPath('data.0.id', $outTransaction->id);
});

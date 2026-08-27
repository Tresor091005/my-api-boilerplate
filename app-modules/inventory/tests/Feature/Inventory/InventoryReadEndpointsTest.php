<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
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
        ThrottleRequests::class,
        ResolveAuthContext::class,
        SetTeamPermissionsId::class,
    ]);
    $this->ensureInventoryTestTables();
    $this->inventoryService = app(InventoryService::class);
    $this->currency = Currency::factory()->create();
    currentTestCase()->configureInventoryCurrency($this->currency->code);
    $this->group = UnitGroup::factory()->create();
    $this->unit = Unit::factory()->create([
        'ratio'    => 1,
        'group_id' => $this->group->id,
    ]);
});

it('returns active lots for an item and location', function (): void {
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

it('returns stock summary and expiring lots with pagination metadata', function (): void {
    $itemA = InventoryItem::factory()->create([
        'base_unit_code' => $this->unit->code,
        'sku'            => 'ITEM-A',
        'is_expirable'   => true,
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
        'unit_cost'       => 100,
        'expiration_date' => now()->addDays(4),
    ]);

    InventoryStock::factory()->for($itemA, 'item')->for($locationA, 'location')->create([
        'unit_code'     => $this->unit->code,
        'currency_code' => $this->currency->code,
        'quantity'      => 30,
        'remaining'     => 30,
        'unit_cost'     => 200,
    ]);

    InventoryStock::factory()->for($itemB, 'item')->for($locationA, 'location')->create([
        'unit_code'       => $this->unit->code,
        'currency_code'   => $this->currency->code,
        'quantity'        => 90,
        'remaining'       => 90,
        'expiration_date' => now()->addDays(2),
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

    $this->getJson("/v1/inventory/stock/summary?item_id[]={$itemA->id}&location_id[]={$locationA->id}")
        ->assertOk()
        ->assertJsonPath('data.0.item_id', $itemA->id)
        ->assertJsonPath('data.0.location_id', $locationA->id)
        ->assertJsonPath('data.0.remaining', 100)
        ->assertJsonPath('data.0.total_value', '130.00')
        ->assertJsonPath('data.0.currency_code', $this->currency->code);

    $this->getJson('/v1/inventory/stock/expiring?days=7')
        ->assertOk()
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']])
        ->assertJsonCount(1, 'data')
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

it('does not expose the removed standalone value endpoints', function (): void {
    $item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $location = InventoryLocation::factory()->create();

    $this->getJson("/v1/inventory/items/{$item->id}/value")->assertNotFound();
    $this->getJson("/v1/inventory/locations/{$location->id}/value")->assertNotFound();
});

it('does not expose inventory item and location registry endpoints', function (): void {
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
    $stock = InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create([
        'unit_code'     => $this->unit->code,
        'currency_code' => $this->currency->code,
    ]);

    $this->getJson('/v1/inventory/items')->assertNotFound();
    $this->getJson("/v1/inventory/items/{$item->id}")->assertNotFound();
    $this->getJson('/v1/inventory/locations')->assertNotFound();
    $this->getJson("/v1/inventory/locations/{$location->id}")->assertNotFound();
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

    $this->getJson("/v1/inventory/movements?item_id[]={$item->id}&location_id[]={$location->id}&movement_type=out&reference_type=sale_order&reference_id={$saleReferenceId}")
        ->assertOk()
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.transaction_id', $outTransaction->id)
        ->assertJsonPath('data.0.movement_type', MovementType::Out->value)
        ->assertJsonPath('data.0.quantity', 35);

    $this->getJson("/v1/inventory/movements?location_id[]={$location->id}")
        ->assertOk()
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']])
        ->assertJsonCount(2, 'data');

    $this->getJson("/v1/inventory/transactions/{$inTransaction->id}?include=movements,movements.stock")
        ->assertOk()
        ->assertJsonPath('data.id', $inTransaction->id)
        ->assertJsonPath('data.transaction_type', TransactionType::In->value)
        ->assertJsonPath('data.movements.0.transaction_id', $inTransaction->id)
        ->assertJsonPath('data.movements.0.total_cost', '450.00')
        ->assertJsonPath('data.movements.0.stock.unit_cost', '4.50')
        ->assertJsonPath('data.movements.0.metadata.batch', 'IN-001');

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

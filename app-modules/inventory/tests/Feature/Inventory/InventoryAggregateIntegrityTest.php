<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Tests\Concerns\InteractsWithInventoryTestFixtures;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;

uses(RefreshDatabase::class, InteractsWithInventoryTestFixtures::class);

beforeEach(function (): void {
    $this->ensureInventoryTestTables();
    $this->currency = Currency::factory()->create();
    $this->unit = Unit::factory()->create();
});

it('rejects a stock whose item belongs to another organization', function (): void {
    $item = InventoryItem::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'base_unit_code'  => $this->unit->code,
    ]);
    $location = InventoryLocation::factory()->create(['organization_id' => $this->organizationId]);

    expect(fn (): mixed => DB::table('inventory_stocks')->insertGetId([
        'id'                => (string) Str::uuid7(),
        'organization_id'   => $this->organizationId,
        'item_id'           => $item->id,
        'location_id'       => $location->id,
        'unit_cost'         => 100,
        'cost_remainder'    => 0,
        'quantity'          => 10,
        'remaining'         => 10,
        'unit_code'         => $this->unit->code,
        'currency_code'     => $this->currency->code,
        'expiration_date'   => null,
        'metadata'          => null,
        'exchange_metadata' => null,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects a movement whose item and location do not match its stock', function (): void {
    $item = InventoryItem::factory()->create([
        'organization_id' => $this->organizationId,
        'base_unit_code'  => $this->unit->code,
    ]);
    $otherItem = InventoryItem::factory()->create([
        'organization_id' => $this->organizationId,
        'base_unit_code'  => $this->unit->code,
    ]);
    $location = InventoryLocation::factory()->create(['organization_id' => $this->organizationId]);
    $stock = InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create();
    $transactionId = (string) Str::uuid7();
    $now = now();

    DB::table('inventory_transactions')->insert([
        'id'               => $transactionId,
        'organization_id'  => $this->organizationId,
        'idempotency_key'  => (string) Str::uuid7(),
        'payload_hash'     => hash('sha256', $transactionId),
        'reference_type'   => 'test_reference',
        'reference_id'     => (string) Str::uuid7(),
        'transaction_type' => 'out',
        'metadata'         => null,
        'created_at'       => $now,
        'updated_at'       => $now,
    ]);

    expect(fn (): mixed => DB::table('inventory_movements')->insertGetId([
        'id'                      => (string) Str::uuid7(),
        'organization_id'         => $this->organizationId,
        'movement_type'           => 'out',
        'transaction_id'          => $transactionId,
        'item_id'                 => $otherItem->id,
        'location_id'             => $location->id,
        'stock_id'                => $stock->id,
        'quantity'                => 1,
        'unit_code'               => $stock->unit_code,
        'total_cost'              => $stock->unit_cost,
        'currency_code'           => $stock->currency_code,
        'expiration_date'         => null,
        'metadata'                => null,
        'exchange_metadata'       => null,
        'stock_metadata_snapshot' => null,
        'created_at'              => $now,
        'updated_at'              => $now,
    ]))->toThrow(QueryException::class);
});

it('creates factory movements with the item and location of their stock', function (): void {
    $item = InventoryItem::factory()->create([
        'organization_id' => $this->organizationId,
        'base_unit_code'  => $this->unit->code,
    ]);
    $location = InventoryLocation::factory()->create(['organization_id' => $this->organizationId]);
    $stock = InventoryStock::factory()
        ->for($item, 'item')
        ->for($location, 'location')
        ->create();
    $transaction = InventoryTransaction::factory()->create([
        'organization_id' => $this->organizationId,
    ]);

    $movement = InventoryMovement::factory()->create([
        'organization_id' => $this->organizationId,
        'transaction_id'  => $transaction->id,
        'stock_id'        => $stock->id,
        'unit_code'       => $stock->unit_code,
        'currency_code'   => $stock->currency_code,
    ]);

    expect($movement->item_id)->toBe($stock->item_id);
    expect($movement->location_id)->toBe($stock->location_id);
});

it('rejects a movement linked to a transaction from another organization', function (): void {
    $item = InventoryItem::factory()->create([
        'organization_id' => $this->organizationId,
        'base_unit_code'  => $this->unit->code,
    ]);
    $location = InventoryLocation::factory()->create(['organization_id' => $this->organizationId]);
    $stock = InventoryStock::factory()
        ->for($item, 'item')
        ->for($location, 'location')
        ->create();
    $transaction = InventoryTransaction::factory()->create([
        'organization_id' => $this->otherOrganizationId,
    ]);

    expect(fn (): InventoryMovement => InventoryMovement::factory()->create([
        'organization_id' => $this->organizationId,
        'transaction_id'  => $transaction->id,
        'stock_id'        => $stock->id,
        'unit_code'       => $stock->unit_code,
        'currency_code'   => $stock->currency_code,
    ]))->toThrow(QueryException::class);
});

it('rejects a reversal linked to a transaction from another organization', function (): void {
    $transaction = InventoryTransaction::factory()->create([
        'organization_id' => $this->organizationId,
    ]);
    $otherTransaction = InventoryTransaction::factory()->create([
        'organization_id' => $this->otherOrganizationId,
    ]);

    expect(fn (): int => DB::table('inventory_transactions')
        ->where('id', $transaction->id)
        ->update(['reversal_of_transaction_id' => $otherTransaction->id]))
        ->toThrow(QueryException::class);
});

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

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

it('uses FEFO strategy correctly', function (): void {
    // Lot 1: Expire later
    $lot1 = InventoryStock::create([
        'item_id'         => $this->item->id,
        'location_id'     => $this->location->id,
        'unit_cost'       => 100,
        'currency_code'   => 'EUR',
        'quantity'        => 100,
        'remaining'       => 100,
        'unit_code'       => $this->unitCode,
        'peremption_date' => now()->addDays(10),
    ]);

    // Lot 2: Expire sooner
    $lot2 = InventoryStock::create([
        'item_id'         => $this->item->id,
        'location_id'     => $this->location->id,
        'unit_cost'       => 100,
        'currency_code'   => 'EUR',
        'quantity'        => 100,
        'remaining'       => 100,
        'unit_code'       => $this->unitCode,
        'peremption_date' => now()->addDays(5),
    ]);

    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->location->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 50,
                'unit_code'   => $this->unitCode,
                'strategy'    => DeductionStrategy::Fefo->value,
            ],
        ],
    ];

    $tx = $this->service->recordTransaction($payload);

    $movement = $tx->movements->first();
    // FEFO should pick lot2 because it expires sooner
    expect($movement->stock_id)->toBe($lot2->id);
    expect($lot2->refresh()->remaining)->toBe(50);
});

it('uses Manual strategy correctly', function (): void {
    $lot1 = InventoryStock::create([
        'item_id'       => $this->item->id,
        'location_id'   => $this->location->id,
        'unit_cost'     => 100,
        'currency_code' => 'EUR',
        'quantity'      => 100,
        'remaining'     => 100,
        'unit_code'     => $this->unitCode,
    ]);

    $lot2 = InventoryStock::create([
        'item_id'       => $this->item->id,
        'location_id'   => $this->location->id,
        'unit_cost'     => 100,
        'currency_code' => 'EUR',
        'quantity'      => 100,
        'remaining'     => 100,
        'unit_code'     => $this->unitCode,
    ]);

    $payload = [
        'reference_type'   => 'order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'item_id'     => $this->item->id,
                'location_id' => $this->location->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 50,
                'unit_code'   => $this->unitCode,
                'strategy'    => DeductionStrategy::Manual->value,
                'stock_ids'   => [$lot2->id], // Manually pick lot 2
            ],
        ],
    ];

    $tx = $this->service->recordTransaction($payload);

    $movement = $tx->movements->first();
    expect($movement->stock_id)->toBe($lot2->id);
    expect($lot2->refresh()->remaining)->toBe(50);
    expect($lot1->refresh()->remaining)->toBe(100);
});

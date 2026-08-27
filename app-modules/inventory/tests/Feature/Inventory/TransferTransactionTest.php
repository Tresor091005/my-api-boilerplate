<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\ReversalException;
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
    $this->ensureInventoryTestTables();
    $this->service = app(InventoryService::class);
    $this->currency = Currency::factory()->create();
    currentTestCase()->configureInventoryCurrency($this->currency->code);
    $this->group = UnitGroup::factory()->create();
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);
    $this->item = InventoryItem::factory()->create([
        'base_unit_code'     => $this->unit->code,
        'deduction_strategy' => DeductionStrategy::Fifo,
    ]);
    $this->locA = InventoryLocation::factory()->create();
    $this->locB = InventoryLocation::factory()->create();
});

function transferPayload(array $movements): array
{
    return [
        'reference_type'   => 'transfer_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Transfer->value,
        'movements'        => $movements,
    ];
}

it('processes each transfer line independently and preserves its link id', function (): void {
    $lot1 = InventoryStock::factory()->for($this->item, 'item')->for($this->locA, 'location')
        ->create(['quantity' => 50, 'remaining' => 50, 'unit_cost' => 1000, 'created_at' => now()->subDay()]);
    $lot2 = InventoryStock::factory()->for($this->item, 'item')->for($this->locA, 'location')
        ->create(['quantity' => 50, 'remaining' => 50, 'unit_cost' => 1200, 'created_at' => now()]);

    $transaction = $this->service->recordTransaction(transferPayload([
        [
            'item_id'        => $this->item->id,
            'location_id'    => $this->locA->id,
            'to_location_id' => $this->locB->id,
            'quantity'       => 70,
            'unit_code'      => $this->unit->code,
        ],
    ]));

    expect($lot1->refresh()->remaining)->toBe(0)
        ->and($lot2->refresh()->remaining)->toBe(30)
        ->and((int) $this->locB->stocks()->sum('remaining'))->toBe(70);

    $movements = $transaction->movements()->get();
    expect($movements)->toHaveCount(4)
        ->and($movements->pluck('link_id')->unique())->toHaveCount(1)
        ->and($movements->where('movement_type', MovementType::In)->pluck('total_cost')->all())
        ->toBe([50000, 24000]);
});

it('supports multiple source-to-destination transfer lines', function (): void {
    $locC = InventoryLocation::factory()->create();
    $locD = InventoryLocation::factory()->create();
    InventoryStock::factory()->for($this->item, 'item')->for($this->locA, 'location')
        ->create(['quantity' => 25, 'remaining' => 25, 'unit_cost' => 1000]);
    InventoryStock::factory()->for($this->item, 'item')->for($locC, 'location')
        ->create(['quantity' => 20, 'remaining' => 20, 'unit_cost' => 1200]);

    $transaction = $this->service->recordTransaction(transferPayload([
        [
            'item_id'        => $this->item->id,
            'location_id'    => $this->locA->id,
            'to_location_id' => $this->locB->id,
            'quantity'       => 25,
            'unit_code'      => $this->unit->code,
        ],
        [
            'item_id'        => $this->item->id,
            'location_id'    => $locC->id,
            'to_location_id' => $locD->id,
            'quantity'       => 20,
            'unit_code'      => $this->unit->code,
        ],
    ]));

    $links = $transaction->movements()->get()->pluck('link_id')->unique();
    expect($links)->toHaveCount(2)
        ->and((int) $this->locB->stocks()->sum('remaining'))->toBe(25)
        ->and((int) $locD->stocks()->sum('remaining'))->toBe(20);
});

it('rejects the old transfer movement shape and invalid routes', function (): void {
    $payload = transferPayload([[
        'item_id'     => $this->item->id,
        'location_id' => $this->locA->id,
        'type'        => MovementType::Out->value,
        'quantity'    => 10,
        'unit_code'   => $this->unit->code,
    ]]);

    $this->service->recordTransaction($payload);
})->throws(ValidationException::class);

it('transfers stock metadata while keeping movement metadata separate', function (): void {
    InventoryStock::factory()->for($this->item, 'item')->for($this->locA, 'location')->create([
        'quantity'  => 10,
        'remaining' => 10,
        'metadata'  => ['batch' => 'LOT-001'],
        'unit_cost' => 1000,
    ]);

    $transaction = $this->service->recordTransaction(transferPayload([[
        'item_id'        => $this->item->id,
        'location_id'    => $this->locA->id,
        'to_location_id' => $this->locB->id,
        'quantity'       => 10,
        'unit_code'      => $this->unit->code,
        'metadata'       => ['reason' => 'rebalance'],
    ]]));

    $destinationStock = $this->locB->stocks()->firstOrFail();
    $destinationMovement = $transaction->movements()->where('movement_type', MovementType::In)->firstOrFail();
    expect($destinationStock->metadata)->toBe(['batch' => 'LOT-001'])
        ->and($destinationMovement->metadata)->toBe(['reason' => 'rebalance']);
});

it('reverses a transfer from its persisted linked allocations', function (): void {
    InventoryStock::factory()->for($this->item, 'item')->for($this->locA, 'location')->create([
        'quantity' => 10, 'remaining' => 10, 'unit_cost' => 1000,
    ]);

    $transaction = $this->service->recordTransaction(transferPayload([[
        'item_id'        => $this->item->id,
        'location_id'    => $this->locA->id,
        'to_location_id' => $this->locB->id,
        'quantity'       => 10,
        'unit_code'      => $this->unit->code,
    ]]));

    $reversal = $this->service->reverseTransaction($transaction->id);

    expect((int) $this->locB->stocks()->sum('remaining'))->toBe(0)
        ->and((int) $this->locA->stocks()->sum('remaining'))->toBe(10)
        ->and($reversal->movements()->where('movement_type', MovementType::In)->value('total_cost'))->toBe(10000)
        ->and($reversal->movements()->pluck('link_id')->unique())->toHaveCount(1);
});

it('refuses to reverse a transfer after its destination lot has been used', function (): void {
    InventoryStock::factory()->for($this->item, 'item')->for($this->locA, 'location')->create([
        'quantity' => 10, 'remaining' => 10, 'unit_cost' => 1000,
    ]);

    $transaction = $this->service->recordTransaction(transferPayload([[
        'item_id'        => $this->item->id,
        'location_id'    => $this->locA->id,
        'to_location_id' => $this->locB->id,
        'quantity'       => 10,
        'unit_code'      => $this->unit->code,
    ]]));

    $this->service->recordTransaction([
        'reference_type'   => 'stock_issue',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->locB->id,
            'type'        => MovementType::Out->value,
            'quantity'    => 1,
            'unit_code'   => $this->unit->code,
            'strategy'    => DeductionStrategy::Manual->value,
            'stock_ids'   => [$transaction->movements()->where('movement_type', MovementType::In)->value('stock_id')],
        ]],
    ]);

    $this->service->reverseTransaction($transaction->id);
})->throws(ReversalException::class, 'has already been used');

it('supports transfer quantities expressed in another unit', function (): void {
    $kgUnit = Unit::factory()->create(['ratio' => 1000, 'group_id' => $this->group->id]);
    InventoryStock::factory()->for($this->item, 'item')->for($this->locA, 'location')->create([
        'quantity' => 1000, 'remaining' => 1000, 'unit_cost' => 1000,
    ]);

    $this->service->recordTransaction(transferPayload([[
        'item_id'        => $this->item->id,
        'location_id'    => $this->locA->id,
        'to_location_id' => $this->locB->id,
        'quantity'       => 0.5,
        'unit_code'      => $kgUnit->code,
    ]]));

    expect((int) $this->locA->stocks()->sum('remaining'))->toBe(500)
        ->and((int) $this->locB->stocks()->sum('remaining'))->toBe(500);
});

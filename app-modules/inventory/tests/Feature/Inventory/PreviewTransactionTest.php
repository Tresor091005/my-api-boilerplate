<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Exceptions\ReversalException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
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
    $this->item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $this->source = InventoryLocation::factory()->create();
    $this->destination = InventoryLocation::factory()->create();
});

function previewInPayload(string $idempotencyKey, string $locationId, string $itemId, string $unitCode, string $currencyCode): array
{
    return [
        'reference_type'   => 'purchase_order',
        'idempotency_key'  => $idempotencyKey,
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'item_id'       => $itemId,
            'location_id'   => $locationId,
            'type'          => MovementType::In->value,
            'quantity'      => 10,
            'unit_code'     => $unitCode,
            'total_cost'    => 125.00,
            'currency_code' => $currencyCode,
        ]],
    ];
}

it('previews an IN without persisting and allows the real transaction afterwards', function (): void {
    $payload = previewInPayload(
        fake()->uuid(),
        $this->source->id,
        $this->item->id,
        $this->unit->code,
        $this->currency->code,
    );

    $this->service->previewTransaction($payload);

    expect(InventoryTransaction::query()->count())->toBe(0)
        ->and(InventoryStock::query()->count())->toBe(0);

    $this->service->recordTransaction($payload);

    expect(InventoryTransaction::query()->count())->toBe(1)
        ->and(InventoryStock::query()->count())->toBe(1);
});

it('previews an OUT without changing remaining or cost remainder', function (): void {
    $stock = InventoryStock::factory()
        ->for($this->item, 'item')
        ->for($this->source, 'location')
        ->create([
            'quantity'       => 10,
            'remaining'      => 10,
            'unit_cost'      => 1250,
            'cost_remainder' => 1,
            'currency_code'  => $this->currency->code,
        ]);

    $this->service->previewTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->source->id,
            'type'        => MovementType::Out->value,
            'quantity'    => 4,
            'unit_code'   => $this->unit->code,
            'strategy'    => 'manual',
            'stock_ids'   => [$stock->id],
        ]],
    ]);

    expect($stock->refresh()->remaining)->toBe(10)
        ->and($stock->cost_remainder)->toBe(1)
        ->and(InventoryTransaction::query()->count())->toBe(0)
        ->and($stock->movements()->count())->toBe(0);
});

it('previews a routed transfer without persisting its link or destination lot', function (): void {
    InventoryStock::factory()
        ->for($this->item, 'item')
        ->for($this->source, 'location')
        ->create([
            'quantity'      => 10,
            'remaining'     => 10,
            'unit_cost'     => 1000,
            'currency_code' => $this->currency->code,
        ]);

    $this->service->previewTransaction([
        'reference_type'   => 'transfer_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Transfer->value,
        'movements'        => [[
            'item_id'        => $this->item->id,
            'location_id'    => $this->source->id,
            'to_location_id' => $this->destination->id,
            'quantity'       => 10,
            'unit_code'      => $this->unit->code,
        ]],
    ]);

    expect(InventoryTransaction::query()->count())->toBe(0)
        ->and(InventoryStock::query()->where('location_id', $this->destination->id)->count())->toBe(0)
        ->and((int) $this->source->stocks()->sum('remaining'))->toBe(10);
});

it('previews an IN reversal without persisting the reversal or consuming the lot', function (): void {
    $original = $this->service->recordTransaction(previewInPayload(
        fake()->uuid(),
        $this->source->id,
        $this->item->id,
        $this->unit->code,
        $this->currency->code,
    ));
    $stock = InventoryStock::query()->firstOrFail();
    $transactionCount = InventoryTransaction::query()->count();

    $this->service->previewReversal($original->id, ['reason' => 'cancelled']);

    expect(InventoryTransaction::query()->count())->toBe($transactionCount)
        ->and($stock->refresh()->remaining)->toBe(10)
        ->and($stock->movements()->count())->toBe(1);
});

it('rejects an already reversed transaction during reversal preview', function (): void {
    $original = $this->service->recordTransaction(previewInPayload(
        fake()->uuid(),
        $this->source->id,
        $this->item->id,
        $this->unit->code,
        $this->currency->code,
    ));
    $this->service->reverseTransaction($original->id);

    $this->service->previewReversal($original->id);
})->throws(ReversalException::class);

it('keeps the database unchanged when preview validation or stock checks fail', function (): void {
    expect(fn () => $this->service->previewTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $this->item->id,
            'location_id' => $this->source->id,
            'type'        => MovementType::Out->value,
            'quantity'    => 1,
            'unit_code'   => $this->unit->code,
        ]],
    ]))->toThrow(InsufficientStockException::class);

    expect(InventoryTransaction::query()->count())->toBe(0)
        ->and(InventoryStock::query()->count())->toBe(0);
});

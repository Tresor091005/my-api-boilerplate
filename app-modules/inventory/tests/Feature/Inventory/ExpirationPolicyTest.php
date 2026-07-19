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
use Lahatre\Inventory\Tests\Concerns\InteractsWithInventoryTestFixtures;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithInventoryTestFixtures::class);

beforeEach(function (): void {
    $this->ensureInventoryTestTables();
    $this->service = app(InventoryService::class);
    $this->currency = Currency::factory()->create();
    $this->group = UnitGroup::factory()->create();
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);
    $this->location = InventoryLocation::factory()->create();
});

function expirationPayload(array $movement, string $type = 'in'): array
{
    return [
        'reference_type'   => 'test',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => $type,
        'movements'        => [$movement],
    ];
}

it('requires expiration dates for inbound movements of expirable items', function (): void {
    $item = InventoryItem::factory()->create([
        'base_unit_code' => $this->unit->code,
        'is_expirable' => true,
    ]);

    expect(fn () => $this->service->recordTransaction(expirationPayload([
        'item_id' => $item->id,
        'location_id' => $this->location->id,
        'type' => MovementType::In->value,
        'quantity' => 10,
        'unit_code' => $this->unit->code,
        'total_cost' => 100,
        'currency_code' => $this->currency->code,
    ])))->toThrow(ValidationException::class, 'expiration date is required');
});

it('does not allow public payloads to bypass expiration validation', function (): void {
    $item = InventoryItem::factory()->create([
        'base_unit_code' => $this->unit->code,
        'is_expirable' => true,
    ]);

    expect(fn () => $this->service->recordTransaction([
        ...expirationPayload([
            'item_id' => $item->id,
            'location_id' => $this->location->id,
            'type' => MovementType::In->value,
            'quantity' => 10,
            'unit_code' => $this->unit->code,
            'total_cost' => 100,
            'currency_code' => $this->currency->code,
        ]),
        '_allow_legacy_expiration' => true,
    ]))->toThrow(ValidationException::class, 'expiration date is required');
});

it('rejects expiration dates for inbound movements of non-expirable items', function (): void {
    $item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);

    expect(fn () => $this->service->recordTransaction(expirationPayload([
        'item_id' => $item->id,
        'location_id' => $this->location->id,
        'type' => MovementType::In->value,
        'quantity' => 10,
        'unit_code' => $this->unit->code,
        'total_cost' => 100,
        'currency_code' => $this->currency->code,
        'expiration_date' => now()->addDays(10),
    ])))->toThrow(ValidationException::class, 'prohibited for a non-expirable item');
});

it('uses FEFO automatically for expirable items and puts undated legacy lots last', function (): void {
    $item = InventoryItem::factory()->create([
        'base_unit_code' => $this->unit->code,
        'is_expirable' => true,
    ]);
    $later = InventoryStock::factory()->for($item, 'item')->for($this->location, 'location')->create([
        'quantity' => 10,
        'remaining' => 10,
        'expiration_date' => now()->addDays(10),
    ]);
    $unknown = InventoryStock::factory()->for($item, 'item')->for($this->location, 'location')->create([
        'quantity' => 10,
        'remaining' => 10,
        'expiration_date' => null,
    ]);
    $earlier = InventoryStock::factory()->for($item, 'item')->for($this->location, 'location')->create([
        'quantity' => 10,
        'remaining' => 10,
        'expiration_date' => now()->addDays(5),
    ]);

    $this->service->recordTransaction(expirationPayload([
        'item_id' => $item->id,
        'location_id' => $this->location->id,
        'type' => MovementType::Out->value,
        'quantity' => 15,
        'unit_code' => $this->unit->code,
    ], TransactionType::Out->value));

    expect($earlier->refresh()->remaining)->toBe(0)
        ->and($later->refresh()->remaining)->toBe(5)
        ->and($unknown->refresh()->remaining)->toBe(10);
});

it('rejects incompatible explicit deduction strategies', function (): void {
    $expirable = InventoryItem::factory()->create([
        'base_unit_code' => $this->unit->code,
        'is_expirable' => true,
    ]);
    $nonExpirable = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);

    $base = [
        'location_id' => $this->location->id,
        'type' => MovementType::Out->value,
        'quantity' => 1,
        'unit_code' => $this->unit->code,
        'strategy' => DeductionStrategy::Fifo->value,
    ];

    expect(fn () => $this->service->recordTransaction(expirationPayload($base + ['item_id' => $expirable->id], TransactionType::Out->value)))
        ->toThrow(ValidationException::class, 'FIFO is not available')
        ->and(fn () => $this->service->recordTransaction(expirationPayload(array_replace($base, [
            'item_id' => $nonExpirable->id,
            'strategy' => DeductionStrategy::Fefo->value,
        ]), TransactionType::Out->value)))
        ->toThrow(ValidationException::class, 'FEFO is not available');
});

it('allows expiration toggles without rewriting existing lots', function (): void {
    $material = $this->createTestMaterial();
    $item = $this->service->createItem($material);
    InventoryStock::factory()->for($item, 'item')->for($this->location, 'location')->create([
        'expiration_date' => null,
    ]);

    $item->update(['deduction_strategy' => DeductionStrategy::Fifo]);
    $updated = $this->service->updateItem($material, ['is_expirable' => true]);

    expect($updated->is_expirable)->toBeTrue()
        ->and($updated->deduction_strategy)->toBeNull()
        ->and($item->stocks()->firstOrFail()->refresh()->expiration_date)->toBeNull();

    $updated = $this->service->updateItem($material, ['is_expirable' => false]);

    expect($updated->is_expirable)->toBeFalse();
});

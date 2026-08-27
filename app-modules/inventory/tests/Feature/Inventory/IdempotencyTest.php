<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\IdempotencyKeyReuseException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Services\InventoryService;
use Lahatre\Inventory\Tests\Concerns\InteractsWithInventoryTestFixtures;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Organization\Models\ExchangeRate;

uses(RefreshDatabase::class, InteractsWithInventoryTestFixtures::class);

beforeEach(function (): void {
    $this->ensureInventoryTestTables();
    $this->service = app(InventoryService::class);
    $this->currency = Currency::factory()->create();
    currentTestCase()->configureInventoryCurrency($this->currency->code);
    $this->group = UnitGroup::factory()->create(['is_builtin' => false]);
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);
    $this->item = InventoryItem::factory()->create([
        'base_unit_code' => $this->unit->code,
    ]);
    $this->location = InventoryLocation::factory()->create();
});

function idempotentTransactionPayload(
    InventoryItem $item,
    InventoryLocation $location,
    Unit $unit,
    Currency $currency,
    string $key,
): array {
    return [
        'idempotency_key'  => $key,
        'reference_type'   => 'purchase_order',
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'item_id'       => $item->id,
            'location_id'   => $location->id,
            'type'          => MovementType::In->value,
            'quantity'      => 10,
            'unit_code'     => $unit->code,
            'total_cost'    => 150.00,
            'currency_code' => $currency->code,
        ]],
    ];
}

it('returns the original transaction without duplicating ledger entries on replay', function (): void {
    $payload = idempotentTransactionPayload($this->item, $this->location, $this->unit, $this->currency, 'purchase-order-123');

    $first = $this->service->recordTransaction($payload);
    $second = $this->service->recordTransaction($payload);

    expect($second->id)->toBe($first->id)
        ->and(InventoryTransaction::query()->count())->toBe(1)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(InventoryStock::query()->count())->toBe(1);
});

it('keeps the original conversion snapshot when a converted transaction is replayed', function (): void {
    $transactionCurrency = Currency::factory()->create();
    DB::table('organization_settings')
        ->where('organization_id', $this->organizationId)
        ->update([
            'enable_currencies' => json_encode([$this->currency->code, $transactionCurrency->code], JSON_THROW_ON_ERROR),
        ]);
    $rate = ExchangeRate::factory()->create([
        'organization_id'      => $this->organizationId,
        'source_currency_code' => $transactionCurrency->code,
        'target_currency_code' => $this->currency->code,
        'rate'                 => '2',
        'effective_at'         => now()->subMinute(),
    ]);
    setPermissionsTeamId($this->organizationId);

    $payload = idempotentTransactionPayload(
        $this->item,
        $this->location,
        $this->unit,
        $transactionCurrency,
        'purchase-order-converted-123',
    );

    $first = $this->service->recordTransaction($payload);
    $rate->update(['rate' => '3']);
    $second = $this->service->recordTransaction($payload);
    $movement = $first->movements->firstOrFail();

    expect($second->id)->toBe($first->id)
        ->and($movement->total_cost)->toBe(30000)
        ->and($movement->exchange_metadata['exchange_rate'])->toBe('2.000000000000');
});

it('rejects reuse of an idempotency key with a different payload', function (): void {
    $payload = idempotentTransactionPayload($this->item, $this->location, $this->unit, $this->currency, 'purchase-order-456');
    $this->service->recordTransaction($payload);

    $payload['movements'][0]['quantity'] = 11;

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(IdempotencyKeyReuseException::class);
});

it('requires an idempotency key', function (): void {
    $payload = idempotentTransactionPayload($this->item, $this->location, $this->unit, $this->currency, 'purchase-order-789');
    unset($payload['idempotency_key']);

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class);
});

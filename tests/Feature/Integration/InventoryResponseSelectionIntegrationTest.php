<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Inventory\Http\Resources\InventoryMovementResource;
use Lahatre\Inventory\Http\Resources\InventoryStockResource;
use Lahatre\Inventory\Http\Resources\InventoryTransactionResource;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;
use Lahatre\Organization\Models\Organization;
use Lahatre\ResponseSelection\Testing\AssertsBoundedQueries;
use Lahatre\ResponseSelection\Testing\AssertsResponseFieldIsolation;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class, AssertsBoundedQueries::class, AssertsResponseFieldIsolation::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'Inventory Selection Organization']);
    setPermissionsTeamId($this->organization->id);

    $user = User::factory()->create();
    $member = OrganizationMember::create(['user_id' => $user->id, 'organization_id' => $this->organization->id]);
    $role = Role::query()->firstOrCreate(['name' => 'inventory-selection-reader', 'guard_name' => 'sanctum']);
    $memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'role_id'         => $role->id,
    ]);

    $permissions = [
        'inventory_stock.list', 'inventory_movement.list', 'inventory_transaction.list', 'inventory_transaction.retrieve',
    ];
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $memberRole->givePermissionTo($permissions);

    $token = $user->createToken('inventory-selection-token');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);

    $this->currency = Currency::factory()->create();
    $unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
    $this->unit = Unit::factory()->create(['organization_id' => null, 'group_id' => $unitGroup->id, 'ratio' => 1]);
    // costs and units are read through the master cache, which must know the new rows
    app(UnitCache::class)->rewarmUnits();
    app(UnitCache::class)->rewarmCurrencies();
});

/**
 * A movement of its own transaction, on an expiring stock lot whose unit and currency exist, so
 * every inventory include has a row.
 */
function inventorySelectionMovement(Unit $unit, Currency $currency): InventoryMovement
{
    $item = InventoryItem::factory()->create(['base_unit_code' => $unit->code, 'is_expirable' => true]);
    $stock = InventoryStock::factory()->for($item, 'item')->create([
        'currency_code'   => $currency->code,
        'cost_remainder'  => 3,
        'expiration_date' => today()->addDays(5),
        'metadata'        => ['lot_number' => 'LOT-1'],
    ]);

    return InventoryMovement::factory()->create([
        'stock_id'        => $stock->id,
        'currency_code'   => $currency->code,
        'expiration_date' => today()->addDays(5),
        'metadata'        => ['batch' => 'IN-001'],
    ]);
}

it('renders every field of the inventory reads alone as in the full selection', function (): void {
    $movement = inventorySelectionMovement($this->unit, $this->currency);

    $this->assertFieldsRenderInIsolation('/v1/inventory/stocks', InventoryStockResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/inventory/movements', InventoryMovementResource::class, 1);
    $this->assertFieldsRenderInIsolation('/v1/inventory/transactions', InventoryTransactionResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/inventory/transactions/{$movement->transaction_id}", InventoryTransactionResource::class, 1);
});

it('runs as many queries for more inventory rows with their includes', function (string $uri): void {
    $this->assertBoundedQueries(
        fn (int $count) => collect(range(1, $count))->each(fn () => inventorySelectionMovement($this->unit, $this->currency)),
        fn () => $this->getJson($uri)->assertOk(),
    );
})->with([
    'stocks'       => ['/v1/inventory/stocks?include=location,unit.group,currency,movements'],
    'movements'    => ['/v1/inventory/movements?include=location,stock,unit.group,currency'],
    'transactions' => ['/v1/inventory/transactions?include=movements.location,movements.stock,movements.unit.group,movements.currency'],
]);

it('renders stock costs in the stock currency and its civil expiration date', function (): void {
    $movement = inventorySelectionMovement($this->unit, $this->currency);
    $stock = InventoryStock::query()->findOrFail($movement->stock_id);
    $precision = (int) $this->currency->precision;
    $amount = fn (int $minor): string => bcdiv((string) $minor, bcpow('10', (string) $precision), $precision);

    $this->getJson("/v1/inventory/transactions/{$movement->transaction_id}?fields=id&include=movements(total_cost,expiration_date),movements.stock(unit_cost,total_cost,cost_remainder,days_remaining)")
        ->assertOk()
        ->assertJsonPath('data.movements.0.total_cost', $amount((int) $movement->total_cost))
        ->assertJsonPath('data.movements.0.expiration_date', today()->addDays(5)->toDateString())
        ->assertJsonPath('data.movements.0.stock.unit_cost', $amount((int) $stock->unit_cost))
        ->assertJsonPath('data.movements.0.stock.total_cost', $amount(($stock->remaining * $stock->unit_cost) + 3))
        ->assertJsonPath('data.movements.0.stock.cost_remainder', $amount(3))
        ->assertJsonPath('data.movements.0.stock.days_remaining', 5);
});

it('shows only movements of the caller organization under stocks and transactions', function (): void {
    $movement = inventorySelectionMovement($this->unit, $this->currency);

    // the database refuses cross-organization links, so the foreign rows live in their own aggregate
    $otherOrganization = Organization::factory()->create();
    setPermissionsTeamId($otherOrganization->id);
    $foreignMovement = inventorySelectionMovement($this->unit, $this->currency);
    setPermissionsTeamId($this->organization->id);

    $stocks = $this->getJson('/v1/inventory/stocks?fields=id&include=movements(id)')->assertOk();
    $transaction = $this->getJson("/v1/inventory/transactions/{$movement->transaction_id}?fields=id&include=movements(id)")->assertOk();
    $transactions = $this->getJson('/v1/inventory/transactions?fields=id&include=movements(id)')->assertOk();

    expect($stocks->json('data'))->toBe([['id' => $movement->stock_id, 'movements' => [['id' => $movement->id]]]])
        ->and($transaction->json('data.movements'))->toBe([['id' => $movement->id]])
        ->and($transactions->json('data'))->toBe([['id' => $movement->transaction_id, 'movements' => [['id' => $movement->id]]]]);

    $this->getJson("/v1/inventory/transactions/{$foreignMovement->transaction_id}")->assertForbidden();
});

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Data\InventoryItemConfigurationData;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Exceptions\InventoryItemException;
use Lahatre\Inventory\Exceptions\OrganizationScopeException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\Inventory\Services\InventoryService;
use Lahatre\Inventory\Services\Stock\ManageInventoryStockService;
use Lahatre\Inventory\Tests\Concerns\InteractsWithInventoryTestFixtures;
use Lahatre\Inventory\Tests\Fixtures\TestInventoryMaterial;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithInventoryTestFixtures::class);

beforeEach(function (): void {
    $this->ensureInventoryTestTables();

    $this->service = app(InventoryService::class);
    $this->currency = Currency::factory()->create();
    $this->group = UnitGroup::factory()->create(['is_builtin' => false]);
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);
});

it('createManyItems supports mixed morph types through the resolver', function (): void {
    $variantA = $this->createTestMaterial();
    $variantB = $this->createTestAltMaterial();

    $items = $this->service->createManyItems([$variantA, $variantB]);

    expect($items)->toHaveCount(2)
        ->and(InventoryItem::query()
            ->whereIn('itemable_type', [$variantA->getMorphClass(), $variantB->getMorphClass()])
            ->whereIn('itemable_id', [$variantA->getKey(), $variantB->getKey()])
            ->count())->toBe(2);
});

it('rejects an itemable from another organization', function (): void {
    $foreignMaterial = $this->createTestMaterial([
        'organization_id' => $this->otherOrganizationId,
    ]);

    expect(fn () => $this->service->createItem($foreignMaterial))
        ->toThrow(OrganizationScopeException::class);
});

it('uses persisted host ownership for falsified and partially hydrated models', function (): void {
    $material = $this->createTestMaterial();
    $material->organization_id = $this->otherOrganizationId;

    $item = $this->service->createItem($material);

    $partialMaterial = TestInventoryMaterial::query()
        ->select(['id'])
        ->findOrFail($material->id);
    $partialItem = $this->service->createItem($partialMaterial);

    expect($item->organization_id)->toBe($this->organizationId)
        ->and($partialItem->id)->toBe($item->id);
});

it('scopes polymorphic inventory relations to the current organization', function (): void {
    $material = $this->createTestMaterial();
    $warehouse = $this->createTestWarehouse();
    $item = $this->service->createItem($material);
    $location = $this->service->createLocation($warehouse);

    setPermissionsTeamId($this->otherOrganizationId);

    expect($material->fresh()->inventoryItem)->toBeNull()
        ->and($warehouse->fresh()->inventoryLocation)->toBeNull();

    setPermissionsTeamId($this->organizationId);

    expect($material->fresh()->inventoryItem?->id)->toBe($item->id)
        ->and($warehouse->fresh()->inventoryLocation?->id)->toBe($location->id);
});

it('scopes descendant relations and rejects forged stock updates', function (): void {
    $material = $this->createTestMaterial();
    $warehouse = $this->createTestWarehouse();
    $item = $this->service->createItem($material);
    $location = $this->service->createLocation($warehouse);
    $stock = InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create();

    setPermissionsTeamId($this->otherOrganizationId);

    $foreignMaterial = $this->createTestMaterial([
        'organization_id' => $this->otherOrganizationId,
    ]);
    $foreignWarehouse = $this->createTestWarehouse([
        'organization_id' => $this->otherOrganizationId,
    ]);
    $foreignItem = InventoryItem::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'base_unit_code'  => $this->unit->code,
    ]);
    $foreignLocation = InventoryLocation::factory()->create([
        'organization_id' => $this->otherOrganizationId,
    ]);
    $foreignStock = InventoryStock::factory()
        ->for($foreignItem, 'item')
        ->for($foreignLocation, 'location')
        ->create();

    setPermissionsTeamId($this->organizationId);

    $crossTenantItemLink = InventoryItem::factory()->create([
        'organization_id' => $this->organizationId,
        'itemable_type'   => $foreignMaterial->getMorphClass(),
        'itemable_id'     => $foreignMaterial->id,
        'base_unit_code'  => $this->unit->code,
    ]);
    $crossTenantLocationLink = InventoryLocation::factory()->create([
        'organization_id' => $this->organizationId,
        'external_type'   => $foreignWarehouse->getMorphClass(),
        'external_id'     => $foreignWarehouse->id,
    ]);

    expect($item->fresh()->stocks)->toHaveCount(1)
        ->and($location->fresh()->stocks)->toHaveCount(1)
        ->and($crossTenantItemLink->itemable)->toBeNull()
        ->and($crossTenantLocationLink->external)->toBeNull()
        ->and(InventoryItem::query()->with('itemable')->findOrFail($crossTenantItemLink->id)->itemable)->toBeNull()
        ->and(InventoryLocation::query()->with('external')->findOrFail($crossTenantLocationLink->id)->external)->toBeNull()
        ->and($foreignStock->setAttribute('organization_id', $this->organizationId))
        ->and(fn () => app(ManageInventoryStockService::class)->updateMetadata($foreignStock, ['forged' => true]))
        ->toThrow(OrganizationScopeException::class);

    expect($foreignStock->refresh()->metadata)->toBeNull()
        ->and(app(InventoryQueryService::class)->retrieveItem($item->setAttribute('organization_id', $this->otherOrganizationId))->id)
        ->toBe($item->id);
});

it('rejects inventory operations without an organization context', function (): void {
    $material = $this->createTestMaterial();
    setPermissionsTeamId(null);

    expect(fn () => $this->service->createItem($material))
        ->toThrow(AuthorizationException::class);
});

it('requires an organization context for empty batches', function (): void {
    setPermissionsTeamId(null);

    expect(fn () => $this->service->createManyItems([]))
        ->toThrow(AuthorizationException::class);
});

it('rejects foreign organization models for update and delete', function (): void {
    setPermissionsTeamId($this->otherOrganizationId);

    $foreignMaterial = $this->createTestMaterial([
        'organization_id' => $this->otherOrganizationId,
    ]);
    $foreignWarehouse = $this->createTestWarehouse([
        'organization_id' => $this->otherOrganizationId,
    ]);
    $this->service->createItem($foreignMaterial);
    $this->service->createLocation($foreignWarehouse);

    setPermissionsTeamId($this->organizationId);

    expect(fn () => $this->service->updateItem($foreignMaterial, ['sku' => 'forbidden']))
        ->toThrow(OrganizationScopeException::class)
        ->and(fn () => $this->service->deleteItem($foreignMaterial))
        ->toThrow(OrganizationScopeException::class)
        ->and(fn () => $this->service->updateLocation($foreignWarehouse, ['is_active' => false]))
        ->toThrow(OrganizationScopeException::class)
        ->and(fn () => $this->service->deleteLocation($foreignWarehouse))
        ->toThrow(OrganizationScopeException::class);
});

it('createManyLocations skips already existing external_id/type pairs without failing', function (): void {
    $companyA = $this->createTestWarehouse();
    $companyB = $this->createTestWarehouse();

    InventoryLocation::factory()->create([
        'external_type' => $companyA->getMorphClass(),
        'external_id'   => $companyA->getKey(),
    ]);

    $locations = $this->service->createManyLocations([$companyA, $companyB]);

    expect($locations)->toHaveCount(2)
        ->and(InventoryLocation::query()
            ->where('external_type', $companyA->getMorphClass())
            ->whereIn('external_id', [$companyA->getKey(), $companyB->getKey()])
            ->count())->toBe(2);
});

it('updateItem validates the deduction_strategy enum', function (): void {
    $variant = $this->createTestMaterial();

    $this->service->createItem($variant);

    expect(fn () => $this->service->updateItem($variant, ['deduction_strategy' => 'invalid']))
        ->toThrow(ValidationException::class);

    $this->service->updateItem($variant, [
        'is_expirable'       => true,
        'deduction_strategy' => DeductionStrategy::Fefo->value,
    ]);
    expect($variant->inventoryItem->refresh()->deduction_strategy)->toBe(DeductionStrategy::Fefo);

    $this->service->updateItem($variant, ['deduction_strategy' => null]);
    expect($variant->inventoryItem->refresh()->deduction_strategy)->toBeNull();
});

it('validates inventory configuration before creating an item', function (): void {
    $material = $this->createTestMaterial();

    expect(fn () => $this->service->createItem(
        $material,
        new InventoryItemConfigurationData(
            isExpirable: false,
            deductionStrategy: DeductionStrategy::Fefo,
        ),
    ))->toThrow(ValidationException::class);

    expect(InventoryItem::query()->where('itemable_id', $material->id)->exists())->toBeFalse();
});

it('only disables stock tracking when no active stock remains', function (): void {
    $material = $this->createTestMaterial();
    $item = $this->service->createItem($material);
    $location = $this->service->createLocation($this->createTestWarehouse());
    $stock = InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create([
        'remaining' => 5,
    ]);

    expect(fn () => $this->service->updateItem($material, ['stock_tracking_enabled' => false]))
        ->toThrow(InventoryItemException::class);

    $stock->update(['remaining' => 0]);
    $updated = $this->service->updateItem($material, ['stock_tracking_enabled' => false]);

    expect($updated->stock_tracking_enabled)->toBeFalse();
});

it('deleteItem and deleteLocation perform a soft delete and preserve stock history', function (): void {
    $variant = $this->createTestMaterial();
    $locationModel = $this->createTestWarehouse();

    $item = $this->service->createItem($variant);
    $location = $this->service->createLocation($locationModel);
    $stock = InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create();

    expect(fn () => $this->service->deleteItem($variant))
        ->toThrow(InventoryItemException::class);

    $stock->update(['remaining' => 0]);
    $this->service->deleteItem($variant);
    $this->service->deleteLocation($locationModel);

    expect(InventoryItem::withTrashed()->find($item->id)?->trashed())->toBeTrue()
        ->and(InventoryLocation::withTrashed()->find($location->id)?->trashed())->toBeTrue()
        ->and(InventoryStock::query()->find($stock->id)?->item_id)->toBe($item->id)
        ->and(InventoryStock::query()->find($stock->id)?->location_id)->toBe($location->id);
});

it('ensures all stock records are locked for update during a transaction', function (): void {
    $item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $location = InventoryLocation::factory()->create();
    InventoryStock::factory()->count(2)->for($item, 'item')->for($location, 'location')->create();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->service->recordTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'item_id'     => $item->id,
            'location_id' => $location->id,
            'type'        => MovementType::Out->value,
            'quantity'    => 10,
            'unit_code'   => $this->unit->code,
            'strategy'    => DeductionStrategy::Fifo->value,
        ]],
    ]);

    expect(collect($queries)->contains(fn (string $sql): bool => str_contains(strtolower($sql), 'for update')))->toBeTrue();
});

it('rolls back all changes if a business logic error occurs in the middle of a multi-movement transaction', function (): void {
    $item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $location = InventoryLocation::factory()->create();
    $stock = InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create([
        'quantity'  => 50,
        'remaining' => 50,
    ]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'sale_order',
        'idempotency_key'  => fake()->uuid(),
        'reference_id'     => Str::uuid7()->toString(),
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'item_id'     => $item->id,
                'location_id' => $location->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 30,
                'unit_code'   => $this->unit->code,
            ],
            [
                'item_id'     => $item->id,
                'location_id' => $location->id,
                'type'        => MovementType::Out->value,
                'quantity'    => 30,
                'unit_code'   => $this->unit->code,
            ],
        ],
    ]))->toThrow(InsufficientStockException::class);

    expect($stock->refresh()->remaining)->toBe(50)
        ->and(InventoryTransaction::query()->count())->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(0);
});

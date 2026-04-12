<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\Models\Product;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Services\InventoryService;
use Lahatre\Inventory\Tests\Fixtures\TestInventoryAltVariant;
use Lahatre\Inventory\Tests\Fixtures\TestInventoryCompany;
use Lahatre\Inventory\Tests\Fixtures\TestInventoryVariant;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Setup Organization context
    $this->organization = Organization::factory()->create();
    setPermissionsTeamId($this->organization->id);

    $this->service = app(InventoryService::class);
    $this->currency = Currency::factory()->create();
    $this->group = UnitGroup::factory()->create(['is_builtin' => false]);
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);
});

it('createManyItems supports mixed morph types through the resolver', function (): void {
    $variantA = TestInventoryVariant::query()->create([
        'organization_id'     => $this->organization->id,
        'product_id'          => Product::factory()->create(['organization_id' => $this->organization->id])->id,
        'sku'                 => fake()->unique()->bothify('SKU-####-????'),
        'unit_group_id'       => $this->group->id,
        'should_manage_stock' => true,
        'is_active'           => true,
    ]);
    $variantB = TestInventoryAltVariant::query()->create([
        'organization_id'     => $this->organization->id,
        'product_id'          => Product::factory()->create(['organization_id' => $this->organization->id])->id,
        'sku'                 => fake()->unique()->bothify('SKU-####-????'),
        'unit_group_id'       => $this->group->id,
        'should_manage_stock' => true,
        'is_active'           => true,
    ]);

    $items = $this->service->createManyItems([$variantA, $variantB]);

    expect($items)->toHaveCount(2)
        ->and(InventoryItem::query()
            ->whereIn('itemable_type', [$variantA->getMorphClass(), $variantB->getMorphClass()])
            ->whereIn('itemable_id', [$variantA->getKey(), $variantB->getKey()])
            ->count())->toBe(2);
});

it('createManyLocations skips already existing external_id/type pairs without failing', function (): void {
    $companyA = TestInventoryCompany::query()->create(['name' => fake()->company()]);
    $companyB = TestInventoryCompany::query()->create(['name' => fake()->company()]);

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
    $variant = TestInventoryVariant::query()->create([
        'organization_id'     => $this->organization->id,
        'product_id'          => Product::factory()->create(['organization_id' => $this->organization->id])->id,
        'sku'                 => fake()->unique()->bothify('SKU-####-????'),
        'unit_group_id'       => $this->group->id,
        'should_manage_stock' => true,
        'is_active'           => true,
    ]);

    $this->service->createItem($variant);

    expect(fn () => $this->service->updateItem($variant, ['deduction_strategy' => 'invalid']))
        ->toThrow(ValidationException::class);

    $this->service->updateItem($variant, ['deduction_strategy' => DeductionStrategy::Fefo->value]);
    expect($variant->inventoryItem->refresh()->deduction_strategy)->toBe(DeductionStrategy::Fefo);

    $this->service->updateItem($variant, ['deduction_strategy' => null]);
    expect($variant->inventoryItem->refresh()->deduction_strategy)->toBeNull();
});

it('deleteItem and deleteLocation perform a soft delete and preserve stock history', function (): void {
    $variant = TestInventoryVariant::query()->create([
        'organization_id'     => $this->organization->id,
        'product_id'          => Product::factory()->create(['organization_id' => $this->organization->id])->id,
        'sku'                 => fake()->unique()->bothify('SKU-####-????'),
        'unit_group_id'       => $this->group->id,
        'should_manage_stock' => true,
        'is_active'           => true,
    ]);
    $locationModel = TestInventoryCompany::query()->create(['name' => fake()->company()]);

    $item = $this->service->createItem($variant);
    $location = $this->service->createLocation($locationModel);
    $stock = InventoryStock::factory()->for($item, 'item')->for($location, 'location')->create();

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

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Inventory\Http\Resources\InventoryLocationResource;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Tests\Concerns\InteractsWithInventoryTestFixtures;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithInventoryTestFixtures::class);

beforeEach(function (): void {
    $this->ensureInventoryTestTables();
    $this->group = UnitGroup::factory()->create(['is_builtin' => false]);
    $this->unit = Unit::factory()->create(['group_id' => $this->group->id]);
});

it('resolves only the current organization inventory item through the morph relation', function (): void {
    $material = $this->createTestMaterial();
    $item = InventoryItem::factory()->create([
        'itemable_type'  => $material->getMorphClass(),
        'itemable_id'    => $material->id,
        'base_unit_code' => $this->unit->code,
    ]);
    InventoryItem::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'itemable_type'   => $material->getMorphClass(),
        'itemable_id'     => $material->id,
        'base_unit_code'  => $this->unit->code,
    ]);

    expect($material->inventoryItem?->id)->toBe($item->id);
});

it('resolves only the current organization inventory location through the morph relation', function (): void {
    $warehouse = $this->createTestWarehouse();
    $location = InventoryLocation::factory()->create([
        'external_type' => $warehouse->getMorphClass(),
        'external_id'   => $warehouse->id,
    ]);
    InventoryLocation::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'external_type'   => $warehouse->getMorphClass(),
        'external_id'     => $warehouse->id,
    ]);

    expect($warehouse->inventoryLocation?->id)->toBe($location->id);
});

it('renders a location without stock or polymorphic data', function (): void {
    $warehouse = $this->createTestWarehouse();
    $location = InventoryLocation::factory()->create([
        'external_type' => $warehouse->getMorphClass(),
        'external_id'   => $warehouse->id,
    ]);
    $data = InventoryLocationResource::make($location)->resolve();

    expect($data)->toHaveKeys([
        'id',
        'external_type',
        'external_id',
        'is_active',
        'created_at',
        'updated_at',
    ]);

    foreach (['stocks', 'total_remaining', 'active_lots_count', 'items', 'external', 'movements'] as $key) {
        expect(array_key_exists($key, $data))->toBeFalse();
    }
});

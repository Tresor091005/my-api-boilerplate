<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Data\InventoryItemConfigurationData;
use Lahatre\Inventory\Data\InventoryLocationConfigurationData;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Tests\Concerns\InteractsWithInventoryTestFixtures;
use Lahatre\Inventory\ViewData\StockLotViewData;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class, InteractsWithInventoryTestFixtures::class);

beforeEach(function (): void {
    $this->ensureInventoryTestTables();
    $this->inventory = app(InventoryInterface::class);
    $this->currency = Currency::factory()->create();
    currentTestCase()->configureInventoryCurrency($this->currency->code);
    $this->group = UnitGroup::factory()->create(['is_builtin' => false]);
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);
});

it('reads the open lots of items at locations, with tracking and activity, in three queries', function (): void {
    $rice = $this->createTestMaterial();
    $oil = $this->createTestMaterial();
    $untracked = $this->createTestMaterial();
    $main = $this->createTestWarehouse();
    $closed = $this->createTestWarehouse();
    $riceItem = $this->inventory->createItem($rice);
    $this->inventory->createItem($oil);
    $this->inventory->createItem($untracked, InventoryItemConfigurationData::fromArray(['stock_tracking_enabled' => false]));
    $mainLocation = $this->inventory->createLocation($main);
    $closedLocation = $this->inventory->createLocation($closed, InventoryLocationConfigurationData::fromArray(['is_active' => false]));
    $lot = fn (string $locationId, int $remaining): InventoryStock => InventoryStock::factory()->create([
        'organization_id' => $this->organizationId,
        'item_id'         => $riceItem->id,
        'location_id'     => $locationId,
        'currency_code'   => $this->currency->code,
        'base_unit_code'  => $this->unit->code,
        'quantity'        => max($remaining, 1),
        'remaining'       => $remaining,
    ]);
    $first = $lot($mainLocation->id, 4);
    $lot($mainLocation->id, 6);
    $lot($mainLocation->id, 0);
    $lot($closedLocation->id, 3);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $availability = $this->inventory->stockAvailability(collect([$rice, $oil, $untracked]), collect([$main, $closed]));
    DB::disableQueryLog();

    expect(count(DB::getQueryLog()))->toBe(3)
        ->and($availability->available($rice->id, $main->id))->toBe(10)
        ->and($availability->available($rice->id, $closed->id))->toBe(3)
        ->and($availability->available($oil->id, $main->id))->toBe(0)
        ->and($availability->isTracked($rice->id))->toBeTrue()
        ->and($availability->isTracked($untracked->id))->toBeFalse()
        ->and($availability->activeLocations->all())->toBe([$main->id => true, $closed->id => false])
        ->and($availability->lots)->toHaveCount(3)
        ->and($availability->lots->get($first->id))->toBeInstanceOf(StockLotViewData::class)
        ->and($availability->lots->get($first->id)->locationId)->toBe($main->id);
});

it('reads nothing of another organization', function (): void {
    $rice = $this->createTestMaterial();
    $main = $this->createTestWarehouse();
    $this->inventory->createItem($rice);
    $this->inventory->createLocation($main);

    setPermissionsTeamId($this->otherOrganizationId);
    $availability = $this->inventory->stockAvailability(collect([$rice]), collect([$main]));

    expect($availability->trackedItems)->toBeEmpty()
        ->and($availability->activeLocations)->toBeEmpty();
});

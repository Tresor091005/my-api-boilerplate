<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Traits\InteractsWithInventory;
use Lahatre\Inventory\Traits\InteractsWithInventoryLocation;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;

class TestProduct extends Model implements HasInventoryItem
{
    use InteractsWithInventory;

    protected $fillable = ['id', 'unit_group_id'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($model) => $model->id = (string) Str::uuid7());
    }

    public function getUnitGroupId(): string
    {
        return (string) $this->unit_group_id;
    }
}

class TestWarehouse extends Model implements HasInventoryLocation
{
    use InteractsWithInventoryLocation;

    protected $fillable = ['id'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($model) => $model->id = (string) Str::uuid7());
    }
}

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Relation::morphMap([
        'test_product'   => TestProduct::class,
        'test_warehouse' => TestWarehouse::class,
    ]);

    Schema::create('test_products', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('unit_group_id');
        $table->timestamps();
    });

    Schema::create('test_warehouses', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->timestamps();
    });

    // Setup Master Data
    $this->currency = Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€', 'precision' => 2]);
    $group = UnitGroup::firstOrCreate(['name' => 'Weight'], ['is_builtin' => false]);
    $this->unitCode = 'test-g';
    $this->unit = Unit::firstOrCreate(['code' => $this->unitCode], ['name' => 'Gram', 'ratio' => 1, 'group_id' => $group->id]);

    app(UnitCache::class)->rewarmUnits();
    app(UnitCache::class)->rewarmCurrencies();
});

it('can auto-create inventory item and location', function () {
    $product = TestProduct::create(['unit_group_id' => UnitGroup::firstWhere('name', 'Weight')->id]);
    $warehouse = TestWarehouse::create();

    $product->recordInventoryIn($warehouse, 10, $this->unitCode, 100, 'EUR');

    expect(InventoryItem::count())->toBe(1);
    expect(InventoryLocation::count())->toBe(1);

    $item = InventoryItem::first();
    expect($item->itemable_id)->toBe((string) $product->id);

    $location = InventoryLocation::first();
    expect($location->external_id)->toBe((string) $warehouse->id);
});

it('can access stocks directly from an itemable model', function () {
    $product = TestProduct::create(['unit_group_id' => UnitGroup::firstWhere('name', 'Weight')->id]);
    $warehouse = TestWarehouse::create();

    $product->recordInventoryIn(
        location: $warehouse,
        quantity: 50,
        unitCode: $this->unitCode,
        unitCost: 100,
        currencyCode: 'EUR'
    );

    expect($product->stocks)->toHaveCount(1);
    expect($product->getStock())->toBe(50.0);
    expect($product->getStock($warehouse))->toBe(50.0);
});

it('can access stocks directly from a location model', function () {
    $product = TestProduct::create(['unit_group_id' => UnitGroup::firstWhere('name', 'Weight')->id]);
    $warehouse = TestWarehouse::create();

    $warehouse->recordInventoryIn(
        item: $product,
        quantity: 50,
        unitCode: $this->unitCode,
        unitCost: 100,
        currencyCode: 'EUR'
    );

    expect($warehouse->locationStocks)->toHaveCount(1);
    expect($warehouse->getStock())->toBe(50.0);
    expect($warehouse->getStock($product))->toBe(50.0);
});

it('can record inventory out from an itemable model', function () {
    $product = TestProduct::create(['unit_group_id' => UnitGroup::firstWhere('name', 'Weight')->id]);
    $warehouse = TestWarehouse::create();

    $product->recordInventoryIn($warehouse, 100, $this->unitCode, 100, 'EUR');
    expect($product->getStock())->toBe(100.0);

    $product->recordInventoryOut($warehouse, 40, $this->unitCode);
    expect($product->getStock())->toBe(60.0);
});

it('can record inventory out from a location model', function () {
    $product = TestProduct::create(['unit_group_id' => UnitGroup::firstWhere('name', 'Weight')->id]);
    $warehouse = TestWarehouse::create();

    $warehouse->recordInventoryIn($product, 100, $this->unitCode, 100, 'EUR');
    expect($warehouse->getStock())->toBe(100.0);

    $warehouse->recordInventoryOut($product, 40, $this->unitCode);
    expect($warehouse->getStock())->toBe(60.0);
});

it('can record inventory adjustment', function () {
    $product = TestProduct::create(['unit_group_id' => UnitGroup::firstWhere('name', 'Weight')->id]);
    $warehouse = TestWarehouse::create();

    $product->recordInventoryIn($warehouse, 100, $this->unitCode, 100, 'EUR');
    expect($product->getStock())->toBe(100.0);

    // Adjust to 50
    $product->recordInventoryAdjustment($warehouse, 50, $this->unitCode, 'EUR');
    expect($product->getStock())->toBe(50.0);

    // Adjust to 120
    $product->recordInventoryAdjustment($warehouse, 120, $this->unitCode, 'EUR');
    expect($product->getStock())->toBe(120.0);
});

<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->cache = new UnitCache();
    $this->group = UnitGroup::factory()->create(['name' => 'Length']);

    $this->baseUnit = Unit::factory()->create([
        'code'     => 'm',
        'name'     => 'Meter',
        'ratio'    => 1,
        'group_id' => $this->group->id,
    ]);

    $this->currency = Currency::factory()->create([
        'code'      => 'EUR',
        'name'      => 'Euro',
        'precision' => 2,
    ]);
});

it('uses a single cache key for all units', function (): void {
    $key = 'master:units:all';
    expect(Cache::has($key))->toBeFalse();

    $unit = $this->cache->getByCode('m');
    expect($unit->id)->toBe($this->baseUnit->id);
    expect(Cache::has($key))->toBeTrue();
});

it('memoizes units collection in memory during the same request', function (): void {
    // First call: loads from DB into Cache and then into local property
    $collection1 = $this->cache->units();

    // Manually clear the shared cache to prove we don't hit it again
    Cache::forget('master:units:all');

    // Second call: should return the exact same instance from the private property
    $collection2 = $this->cache->units();

    expect($collection1)->toBe($collection2); // Exact same object instance
});

it('resets local memory when rewarming units', function (): void {
    $collection1 = $this->cache->units();

    // Add a new unit to DB directly
    Unit::factory()->create(['code' => 'km', 'group_id' => $this->group->id]);

    // Rewarm should clear both local property and shared cache
    $this->cache->rewarmUnits();

    $collection2 = $this->cache->units();

    expect($collection1)->not->toBe($collection2);
    expect($collection2)->toHaveCount(2);
});

it('caches currencies and memoizes them', function (): void {
    $key = 'master:currencies:all';
    expect(Cache::has($key))->toBeFalse();

    $currency1 = $this->cache->getCurrencyByCode('EUR');
    expect($currency1->id)->toBe($this->currency->id);
    expect(Cache::has($key))->toBeTrue();

    $collection1 = $this->cache->currencies();

    // Manually clear shared cache
    Cache::forget($key);

    $collection2 = $this->cache->currencies();
    expect($collection1)->toBe($collection2);
});

it('throws ModelNotFoundException for missing unit code', function (): void {
    $this->cache->getByCode('NON_EXISTENT');
})->throws(ModelNotFoundException::class);

it('throws ModelNotFoundException for missing currency code', function (): void {
    $this->cache->getCurrencyByCode('XYZ');
})->throws(ModelNotFoundException::class);

it('provides units by group id from the cached collection', function (): void {
    $units = $this->cache->getByGroupId($this->group->id);

    expect($units)->toHaveCount(1);
    expect($units->first()->code)->toBe('m');
});

it('provides the base unit from the cached collection', function (): void {
    $baseUnit = $this->cache->getBaseUnit($this->group->id);

    expect($baseUnit->id)->toBe($this->baseUnit->id);
    expect($baseUnit->ratio)->toBe(1);
});

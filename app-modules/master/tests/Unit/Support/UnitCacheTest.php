<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setPermissionsTeamId(null);

    $this->cache = new UnitCache();
    $this->group = UnitGroup::factory()->create([
        'name'            => 'Test Group',
        'organization_id' => null,
    ]);

    $this->baseUnit = Unit::factory()->create([
        'code'            => 'test-m',
        'name'            => 'Test Meter',
        'ratio'           => 1,
        'group_id'        => $this->group->id,
        'organization_id' => null,
    ]);

    $this->currency = Currency::factory()->create([
        'code'      => 'TST',
        'name'      => 'Test Currency',
        'precision' => 2,
    ]);
});

it('uses a single cache key for all units', function (): void {
    $key = 'master:units:all:system';
    expect(Cache::has($key))->toBeFalse();

    $unit = $this->cache->getByCode('test-m');
    expect($unit->id)->toBe($this->baseUnit->id);
    expect(Cache::has($key))->toBeTrue();
});

it('scopes cached units to system and the current organization', function (): void {
    $organizationId = (string) str()->uuid();
    $otherOrganizationId = (string) str()->uuid();
    $now = now();

    DB::table('organization_organizations')->insert([
        [
            'id'         => $organizationId,
            'name'       => 'Cache Test Organization',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ],
        [
            'id'         => $otherOrganizationId,
            'name'       => 'Other Cache Test Organization',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ],
    ]);

    $tenantGroup = UnitGroup::factory()->create([
        'name'            => 'Tenant Group',
        'organization_id' => $organizationId,
    ]);
    $otherTenantGroup = UnitGroup::factory()->create([
        'name'            => 'Other Tenant Group',
        'organization_id' => $otherOrganizationId,
    ]);

    $tenantUnit = Unit::factory()->create([
        'code'            => 'tenant-m',
        'group_id'        => $tenantGroup->id,
        'organization_id' => $organizationId,
    ]);
    Unit::factory()->create([
        'code'            => 'other-tenant-m',
        'group_id'        => $otherTenantGroup->id,
        'organization_id' => $otherOrganizationId,
    ]);

    setPermissionsTeamId($organizationId);

    $tenantCache = new UnitCache();
    $units = $tenantCache->units();

    expect(Cache::has("master:units:all:{$organizationId}"))->toBeTrue()
        ->and($units->keys()->all())->toContain('test-m')
        ->and($units->keys()->all())->toContain('tenant-m')
        ->and($units->keys()->all())->not->toContain('other-tenant-m')
        ->and($tenantCache->getByCode('tenant-m')->id)->toBe($tenantUnit->id);
});

it('memoizes units collection in memory during the same request', function (): void {
    $key = 'master:units:all:system';

    // First call: loads from DB into Cache and then into local property
    $collection1 = $this->cache->units();

    // Manually clear the shared cache to prove we don't hit it again
    Cache::forget($key);

    // Second call: should return the exact same instance from the private property
    $collection2 = $this->cache->units();

    expect($collection1)->toBe($collection2); // Exact same object instance
});

it('resets local memory when rewarming units', function (): void {
    $collection1 = $this->cache->units();

    // Add a new unit to DB directly
    Unit::factory()->create([
        'code'            => 'test-km',
        'group_id'        => $this->group->id,
        'organization_id' => null,
    ]);

    // Rewarm should clear both local property and shared cache
    $this->cache->rewarmUnits();

    $collection2 = $this->cache->units();

    expect($collection1)->not->toBe($collection2);
    expect($collection2->where('code', 'test-km'))->toHaveCount(1);
});

it('caches currencies and memoizes them', function (): void {
    $key = 'master:currencies:all';
    expect(Cache::has($key))->toBeFalse();

    $currency1 = $this->cache->getCurrencyByCode('TST');
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

    // Filter by our test code just in case other units were seeded in the same group (unlikely here but safer)
    expect($units->where('code', 'test-m'))->toHaveCount(1);
});

it('provides the base unit from the cached collection', function (): void {
    $baseUnit = $this->cache->getBaseUnit($this->group->id);

    expect($baseUnit->id)->toBe($this->baseUnit->id);
    expect($baseUnit->ratio)->toBe(1);
});

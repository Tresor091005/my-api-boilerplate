<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\DTO\UnitSyncDTO;
use Lahatre\Catalog\Exceptions\Unit\UnitActiveLimitException;
use Lahatre\Catalog\Exceptions\Unit\UnitBaseDeactivationException;
use Lahatre\Catalog\Exceptions\Unit\UnitBaseRequiredException;
use Lahatre\Catalog\Exceptions\Unit\UnitBuiltInUpdateException;
use Lahatre\Catalog\Exceptions\Unit\UnitDuplicateRatioException;
use Lahatre\Catalog\Exceptions\Unit\UnitRatioConflictException;
use Lahatre\Catalog\Exceptions\Unit\UnitRatioImmutableException;
use Lahatre\Catalog\Models\Unit;
use Lahatre\Catalog\Services\UnitService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can create a new unit group with a base unit', function (): void {
    $payload = [
        'unit_group' => 'Weight',
        'units'      => [
            ['name' => 'Gram', 'symbol' => 'g', 'ratio' => 1, 'is_active' => true],
            ['name' => 'Kilogram', 'symbol' => 'kg', 'ratio' => 1000, 'is_active' => true],
        ],
    ];

    $dto = new UnitSyncDTO($payload);
    $result = app(UnitService::class)->sync($dto);

    expect($result->count())->toBe(2);
    expect(Unit::where('unit_group', 'weight')->count())->toBe(2);
    expect(Unit::where('ratio', 1)->first()->name)->toBe('Gram');
});

it('fails to create a new group without a ratio 1 unit', function (): void {
    $payload = [
        'unit_group' => 'Fail Group',
        'units'      => [
            ['name' => 'No Base', 'ratio' => 10, 'is_active' => true],
        ],
    ];

    $dto = new UnitSyncDTO($payload);
    app(UnitService::class)->sync($dto);
})->throws(UnitBaseRequiredException::class);

it('fails to create a new group with duplicate ratios in payload', function (): void {
    $payload = [
        'unit_group' => 'Duplicate Group',
        'units'      => [
            ['name' => 'Unit 1', 'ratio' => 1, 'is_active' => true],
            ['name' => 'Unit 2', 'ratio' => 1, 'is_active' => true],
        ],
    ];

    $dto = new UnitSyncDTO($payload);
    app(UnitService::class)->sync($dto);
})->throws(UnitDuplicateRatioException::class);

it('can add new units to an existing group', function (): void {
    // Create initial group
    Unit::factory()->create([
        'name'       => 'Meter',
        'ratio'      => 1,
        'unit_group' => 'length',
        'is_active'  => true,
    ]);

    $payload = [
        'unit_group' => 'Length',
        'units'      => [
            ['name' => 'Centimeter', 'ratio' => 10, 'is_active' => true],
        ],
    ];

    $dto = new UnitSyncDTO($payload);
    $result = app(UnitService::class)->sync($dto);

    expect($result->count())->toBe(1);
    expect(Unit::where('unit_group', 'length')->count())->toBe(2);
});

it('fails to add a unit with an existing ratio in the group', function (): void {
    Unit::factory()->create([
        'ratio'      => 1,
        'unit_group' => 'length',
    ]);

    $payload = [
        'unit_group' => 'Length',
        'units'      => [
            ['name' => 'Double Base', 'ratio' => 1, 'is_active' => true],
        ],
    ];

    $dto = new UnitSyncDTO($payload);
    app(UnitService::class)->sync($dto);
})->throws(UnitRatioConflictException::class);

it('can update existing units names and symbols', function (): void {
    $unit = Unit::factory()->create([
        'name'       => 'Old Name',
        'symbol'     => 'ON',
        'ratio'      => 1,
        'unit_group' => 'test-group',
        'is_builtin' => false,
    ]);

    $payload = [
        'unit_group' => 'test-group',
        'units'      => [
            ['id' => $unit->id, 'name' => 'New Name', 'symbol' => 'NN', 'is_active' => true],
        ],
    ];

    $dto = new UnitSyncDTO($payload);
    app(UnitService::class)->sync($dto);

    $unit->refresh();
    expect($unit->name)->toBe('New Name');
    expect($unit->symbol)->toBe('NN');
});

it('fails to update the ratio of an existing unit', function (): void {
    $unit = Unit::factory()->create([
        'ratio'      => 10,
        'unit_group' => 'test-group',
        'is_builtin' => false,
    ]);

    $payload = [
        'unit_group' => 'test-group',
        'units'      => [
            ['id' => $unit->id, 'name' => 'Test', 'ratio' => 20, 'is_active' => true],
        ],
    ];

    $dto = new UnitSyncDTO($payload);
    app(UnitService::class)->sync($dto);
})->throws(UnitRatioImmutableException::class);

it('fails to deactivate the base unit', function (): void {
    $unit = Unit::factory()->create([
        'ratio'      => 1,
        'unit_group' => 'test-group',
        'is_active'  => true,
        'is_builtin' => false,
    ]);

    $payload = [
        'unit_group' => 'test-group',
        'units'      => [
            ['id' => $unit->id, 'name' => 'Test', 'is_active' => false],
        ],
    ];

    $dto = new UnitSyncDTO($payload);
    app(UnitService::class)->sync($dto);
})->throws(UnitBaseDeactivationException::class);

it('fails to update built-in units', function (): void {
    $unit = Unit::factory()->create([
        'unit_group' => 'test-group',
        'is_builtin' => true,
    ]);

    $payload = [
        'unit_group' => 'test-group',
        'units'      => [
            ['id' => $unit->id, 'name' => 'Trying to update', 'is_active' => true],
        ],
    ];

    $dto = new UnitSyncDTO($payload);
    app(UnitService::class)->sync($dto);
})->throws(UnitBuiltInUpdateException::class);

it('enforces the limit of 10 active units per group', function (): void {
    // Create 10 active units
    Unit::factory()->count(10)->create([
        'unit_group' => 'crowded',
        'is_active'  => true,
    ]);

    $payload = [
        'unit_group' => 'Crowded',
        'units'      => [
            ['name' => 'Unit 11', 'ratio' => 100, 'is_active' => true],
        ],
    ];

    $dto = new UnitSyncDTO($payload);
    app(UnitService::class)->sync($dto);
})->throws(UnitActiveLimitException::class);

<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Tests\Concerns\InteractsWithMasterTenantContext;
use Lahatre\Master\Tests\Support\Models\TestTaggableUnit;

uses(RefreshDatabase::class, InteractsWithMasterTenantContext::class);

beforeEach(function (): void {
    $this->initializeMasterTenantContext();

    $group = UnitGroup::factory()->create([
        'organization_id' => $this->organizationId,
    ]);

    $this->unitOne = TestTaggableUnit::query()->findOrFail(Unit::factory()->create([
        'organization_id' => $this->organizationId,
        'group_id'        => $group->id,
        'name'            => 'unit-one',
    ])->id);

    $this->unitTwo = TestTaggableUnit::query()->findOrFail(Unit::factory()->create([
        'organization_id' => $this->organizationId,
        'group_id'        => $group->id,
        'name'            => 'unit-two',
    ])->id);

    $this->unitThree = TestTaggableUnit::query()->findOrFail(Unit::factory()->create([
        'organization_id' => $this->organizationId,
        'group_id'        => $group->id,
        'name'            => 'unit-three',
    ])->id);

    $this->unitOne->attach([
        'status' => ['active'],
        'color'  => ['red'],
    ]);

    $this->unitTwo->attach([
        'status' => ['inactive'],
        'color'  => ['blue'],
    ]);
});

it('filters with any tags for a single type and normalizes input values', function (): void {
    $ids = TestTaggableUnit::query()
        ->whereKey([$this->unitOne->id, $this->unitTwo->id, $this->unitThree->id])
        ->withAnyTagsOfType(' Status ', [' Active ', 'blue'])
        ->pluck('id');

    expect($ids->all())->toEqual([$this->unitOne->id]);
});

it('filters with all tags for a single type', function (): void {
    $ids = TestTaggableUnit::query()
        ->whereKey([$this->unitOne->id, $this->unitTwo->id, $this->unitThree->id])
        ->withAllTagsOfType('status', ['active', 'red'])
        ->pluck('id');

    expect($ids->all())->toBeEmpty();
});

it('filters without tags for a single type', function (): void {
    $ids = TestTaggableUnit::query()
        ->whereKey([$this->unitOne->id, $this->unitTwo->id, $this->unitThree->id])
        ->withoutTagsOfType('status', ['inactive', 'red'])
        ->pluck('id');

    expect($ids->all())->toEqualCanonicalizing([$this->unitOne->id, $this->unitThree->id]);
});

it('keeps query unchanged when given an empty or invalid tags list', function (): void {
    $allIds = TestTaggableUnit::query()->pluck('id')->all();

    $withAnyIds = TestTaggableUnit::query()->withAnyTagsOfType('status', ['', '   '])->pluck('id')->all();
    $withAllIds = TestTaggableUnit::query()->withAllTagsOfType('status', [''])->pluck('id')->all();
    $withoutIds = TestTaggableUnit::query()->withoutTagsOfType('status', ['', '   '])->pluck('id')->all();

    expect($withAnyIds)->toEqualCanonicalizing($allIds)
        ->and($withAllIds)->toEqualCanonicalizing($allIds)
        ->and($withoutIds)->toEqualCanonicalizing($allIds);
});

it('does not mix tag names from another type', function (): void {
    $ids = TestTaggableUnit::query()
        ->whereKey([$this->unitOne->id, $this->unitTwo->id, $this->unitThree->id])
        ->withAnyTagsOfType('status', ['blue'])
        ->pluck('id');

    expect($ids->all())->toBeEmpty();
});

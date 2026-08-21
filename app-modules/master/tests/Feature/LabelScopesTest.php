<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Tests\Concerns\InteractsWithMasterTenantContext;
use Lahatre\Master\Tests\Support\Models\TestLabelableUnit;

uses(RefreshDatabase::class, InteractsWithMasterTenantContext::class);

beforeEach(function (): void {
    $this->initializeMasterTenantContext();

    $group = UnitGroup::factory()->create([
        'organization_id' => $this->organizationId,
    ]);

    $this->unitOne = TestLabelableUnit::query()->findOrFail(Unit::factory()->create([
        'organization_id' => $this->organizationId,
        'group_id'        => $group->id,
        'name'            => 'unit-one',
    ])->id);

    $this->unitTwo = TestLabelableUnit::query()->findOrFail(Unit::factory()->create([
        'organization_id' => $this->organizationId,
        'group_id'        => $group->id,
        'name'            => 'unit-two',
    ])->id);

    $this->unitThree = TestLabelableUnit::query()->findOrFail(Unit::factory()->create([
        'organization_id' => $this->organizationId,
        'group_id'        => $group->id,
        'name'            => 'unit-three',
    ])->id);

    $this->unitOne->attachLabels([
        'status' => ['active'],
        'color'  => ['red'],
    ]);

    $this->unitTwo->attachLabels([
        'status' => ['inactive'],
        'color'  => ['blue'],
    ]);
});

it('filters with any labels for a single group and normalizes input values', function (): void {
    $ids = TestLabelableUnit::query()
        ->whereKey([$this->unitOne->id, $this->unitTwo->id, $this->unitThree->id])
        ->withAnyLabelsOfGroup(' Status ', [' Active ', 'blue'])
        ->pluck('id');

    expect($ids->all())->toEqual([$this->unitOne->id]);
});

it('filters with all labels for a single group', function (): void {
    $ids = TestLabelableUnit::query()
        ->whereKey([$this->unitOne->id, $this->unitTwo->id, $this->unitThree->id])
        ->withAllLabelsOfGroup('status', ['active', 'red'])
        ->pluck('id');

    expect($ids->all())->toBeEmpty();
});

it('filters without labels for a single group', function (): void {
    $ids = TestLabelableUnit::query()
        ->whereKey([$this->unitOne->id, $this->unitTwo->id, $this->unitThree->id])
        ->withoutLabelsOfGroup('status', ['inactive', 'red'])
        ->pluck('id');

    expect($ids->all())->toEqualCanonicalizing([$this->unitOne->id, $this->unitThree->id]);
});

it('keeps query unchanged when given an empty or invalid labels list', function (): void {
    $allIds = TestLabelableUnit::query()->pluck('id')->all();

    $withAnyIds = TestLabelableUnit::query()->withAnyLabelsOfGroup('status', ['', '   '])->pluck('id')->all();
    $withAllIds = TestLabelableUnit::query()->withAllLabelsOfGroup('status', [''])->pluck('id')->all();
    $withoutIds = TestLabelableUnit::query()->withoutLabelsOfGroup('status', ['', '   '])->pluck('id')->all();

    expect($withAnyIds)->toEqualCanonicalizing($allIds)
        ->and($withAllIds)->toEqualCanonicalizing($allIds)
        ->and($withoutIds)->toEqualCanonicalizing($allIds);
});

it('does not mix label values from another group', function (): void {
    $ids = TestLabelableUnit::query()
        ->whereKey([$this->unitOne->id, $this->unitTwo->id, $this->unitThree->id])
        ->withAnyLabelsOfGroup('status', ['blue'])
        ->pluck('id');

    expect($ids->all())->toBeEmpty();
});

it('does not return labelable models from another organization', function (): void {
    $foreignGroup = UnitGroup::factory()->create([
        'organization_id' => $this->otherOrganizationId,
    ]);
    $foreignUnit = TestLabelableUnit::query()->findOrFail(Unit::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'group_id'        => $foreignGroup->id,
    ])->id);

    setPermissionsTeamId($this->otherOrganizationId);
    $foreignUnit->attachLabels(['status' => ['active']]);
    setPermissionsTeamId($this->organizationId);

    $ids = TestLabelableUnit::query()
        ->withAnyLabelsOfGroup('status', ['active'])
        ->pluck('id');

    expect($ids->all())->toEqual([$this->unitOne->id]);
});

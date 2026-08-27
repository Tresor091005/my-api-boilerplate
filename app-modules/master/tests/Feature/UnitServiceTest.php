<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Master\Data\UnitFilterData;
use Lahatre\Master\Data\UnitUpsertData;
use Lahatre\Master\Exceptions\UnitException;
use Lahatre\Master\Http\Requests\UnitUpsertRequest;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Services\UnitService;
use Lahatre\Master\Support\UnitCache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(UnitService::class);
    $this->organizationId = Str::uuid7()->toString();
    setPermissionsTeamId($this->organizationId);
});

it('lists both system units and tenant units but excludes other tenant units', function (): void {
    $otherOrganizationId = Str::uuid7()->toString();

    // 1. System Unit (organization_id is NULL) - use 'a' prefix to stay on first page
    $systemGroup = UnitGroup::factory()->create([
        'name'            => 'system-mass-test',
        'organization_id' => null,
    ]);
    Unit::factory()->create([
        'code'            => 'a-kg-sys',
        'group_id'        => $systemGroup->id,
        'organization_id' => null,
    ]);

    // 2. Our Tenant Unit
    $ourGroup = UnitGroup::factory()->create([
        'name'            => 'our-custom-mass-test',
        'organization_id' => $this->organizationId,
    ]);
    Unit::factory()->create([
        'code'            => 'a-our-kg',
        'group_id'        => $ourGroup->id,
        'organization_id' => $this->organizationId,
    ]);

    // 3. Other Tenant Unit
    $otherGroup = UnitGroup::factory()->create([
        'name'            => 'other-custom-mass-test',
        'organization_id' => $otherOrganizationId,
    ]);
    Unit::factory()->create([
        'code'            => 'a-other-kg',
        'group_id'        => $otherGroup->id,
        'organization_id' => $otherOrganizationId,
    ]);

    app(UnitCache::class)->rewarmUnits();

    $collection = $this->service->paginate(UnitFilterData::fromArray([
        'per_page' => 50,
    ]));
    $codes = collect($collection->items())->pluck('code');

    expect($codes)->toContain('a-kg-sys')
        ->toContain('a-our-kg');
    expect($codes->contains('a-other-kg'))->toBeFalse();
});

it('upserts unit groups and units strictly for the current tenant', function (): void {
    // 1. Create new group and units (should auto-assign organization_id)
    $this->service->upsert(UnitUpsertData::fromArray([
        'group_name' => 'new-tenant-group',
        'units'      => [
            ['name' => 'Unit 1', 'symbol' => 'U1', 'ratio' => 1],
        ],
    ]));

    $group = UnitGroup::where('name', 'new-tenant-group')->first();
    expect($group->organization_id)->toBe($this->organizationId);

    /** @var Unit $unit */
    $unit = $group->units()->firstOrFail();
    expect($unit->organization_id)->toBe($this->organizationId);

    // 2. Prevent syncing/updating a system group at request validation layer
    $systemGroup = UnitGroup::factory()->create([
        'name'            => 'system-group-test',
        'organization_id' => null,
    ]);

    expect(fn (): array => validator([
        'group_id'   => $systemGroup->id,
        'group_name' => 'hacked-name',
    ], new UnitUpsertRequest()->rules())->validate())->toThrow(ValidationException::class);

    // 3. Prevent syncing/updating another tenant's group
    $otherOrganizationId = Str::uuid7()->toString();
    $otherGroup = UnitGroup::factory()->create([
        'name'            => 'other-tenant-group-test',
        'organization_id' => $otherOrganizationId,
    ]);

    expect(fn (): array => validator([
        'group_id'   => $otherGroup->id,
        'group_name' => 'hacked-other-name',
    ], new UnitUpsertRequest()->rules())->validate())->toThrow(ValidationException::class);
});

it('limits custom unit ratios at request and service boundaries', function (): void {
    $maximumRatioPayload = [
        'group_name' => 'maximum-ratio-group',
        'units'      => [
            ['name' => 'Base unit', 'ratio' => 1],
            ['name' => 'Maximum unit', 'ratio' => Unit::MAX_CUSTOM_RATIO],
        ],
    ];

    expect(validator($maximumRatioPayload, new UnitUpsertRequest()->rules())->validate())
        ->toMatchArray($maximumRatioPayload);

    $invalidPayload = [
        'group_name' => 'excessive-ratio-group',
        'units'      => [
            ['name' => 'Base unit', 'ratio' => 1],
            ['name' => 'Excessive unit', 'ratio' => Unit::MAX_CUSTOM_RATIO + 1],
        ],
    ];
    $validator = validator($invalidPayload, new UnitUpsertRequest()->rules());

    expect(fn (): array => $validator->validate())
        ->toThrow(ValidationException::class);
    expect($validator->errors()->keys())->toContain('units.1.ratio');

    expect(fn () => $this->service->upsert(UnitUpsertData::fromArray($invalidPayload)))
        ->toThrow(UnitException::class, 'exceeds the maximum custom ratio');
});

it('reports every invalid synced unit at its original index', function (): void {
    $validator = validator([
        'group_name' => 'indexed-unit-validation',
        'units'      => [
            [
                'id'     => Str::uuid7()->toString(),
                'name'   => 'Missing unit one',
                'symbol' => 'MU1',
                'ratio'  => 1,
            ],
            [
                'id'     => Str::uuid7()->toString(),
                'name'   => 'Missing unit two',
                'symbol' => 'MU2',
                'ratio'  => 1,
            ],
        ],
    ], new UnitUpsertRequest()->rules());

    expect(fn (): array => $validator->validate())
        ->toThrow(ValidationException::class);

    expect($validator->errors()->keys())
        ->toContain('units.0.id')
        ->toContain('units.1.id');
});

it('verifies that unit codes are unique across the entire system', function (): void {
    $otherOrganizationId = Str::uuid7()->toString();

    // Create a unit in another organization with code 'unique-code'
    $otherGroup = UnitGroup::factory()->create(['organization_id' => $otherOrganizationId]);
    Unit::factory()->create([
        'code'            => 'unique-code',
        'group_id'        => $otherGroup->id,
        'organization_id' => $otherOrganizationId,
    ]);

    // Try to create a unit in our organization with the same name
    // The handle generator should detect the code collision and append a suffix.

    $this->service->upsert(UnitUpsertData::fromArray([
        'group_name' => 'our-group-unique',
        'units'      => [
            ['name' => 'unique-code', 'symbol' => 'U1', 'ratio' => 1],
        ],
    ]));

    $unit = Unit::where('name', 'unique-code')->where('organization_id', $this->organizationId)->first();
    expect($unit->code)->toBe('unique-code-1');
});

<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Master\Data\LabelCreateData;
use Lahatre\Master\Data\LabelFilterData;
use Lahatre\Master\Data\LabelReorderData;
use Lahatre\Master\Data\LabelUpdateData;
use Lahatre\Master\Exceptions\LabelException;
use Lahatre\Master\Http\Requests\LabelCreateRequest;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Label;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Services\LabelService;
use Lahatre\Master\Tests\Concerns\InteractsWithMasterTenantContext;
use Lahatre\Master\Tests\Support\Models\TestLabelableCurrency;
use Lahatre\Master\Tests\Support\Models\TestLabelableUnit;

uses(RefreshDatabase::class, InteractsWithMasterTenantContext::class);

beforeEach(function (): void {
    $this->initializeMasterTenantContext();

    $this->unitGroup = UnitGroup::factory()->create([
        'organization_id' => $this->organizationId,
    ]);

    $this->unit = Unit::factory()->create([
        'organization_id' => $this->organizationId,
        'group_id'        => $this->unitGroup->id,
    ]);

    $this->labelableUnit = TestLabelableUnit::query()->findOrFail($this->unit->id);
});

it('attaches labels by group, normalizes values, and avoids duplicates', function (): void {
    $this->labelableUnit->attachLabels([
        'Status' => [' Active ', 'active'],
        'Color'  => ['Red', 'Blue'],
    ]);

    $this->labelableUnit->attachLabels([
        'status' => ['active'],
        'color'  => ['red'],
    ]);

    $labels = $this->labelableUnit->labels()->get();

    expect($labels)->toHaveCount(3)
        ->and($labels->pluck('group')->all())->toEqualCanonicalizing(['status', 'color', 'color'])
        ->and($labels->pluck('value')->all())->toEqualCanonicalizing(['active', 'red', 'blue']);

    expect(
        Label::query()
            ->where('organization_id', $this->organizationId)
            ->count()
    )->toBe(3)
        ->and($this->labelableUnit->labels()->count())->toBe(3);
});

it('creates labels in batches by group and ignores unique conflicts', function (): void {
    $data = LabelCreateData::fromArray([
        'labels' => [
            'Status' => ['Active', 'Inactive'],
            'Color'  => ['Red'],
        ],
    ]);

    $firstResult = app(LabelService::class)->create($data);
    $secondResult = app(LabelService::class)->create($data);

    expect($firstResult)->toHaveCount(3)
        ->and($secondResult)->toHaveCount(3)
        ->and(Label::query()->where('organization_id', $this->organizationId)->count())->toBe(3);
});

it('appends new labels after the existing labels in each group', function (): void {
    app(LabelService::class)->create(LabelCreateData::fromArray([
        'labels' => [
            'status' => ['Active', 'Pending'],
        ],
    ]));

    app(LabelService::class)->create(LabelCreateData::fromArray([
        'labels' => [
            'status' => ['Archived', 'Draft'],
        ],
    ]));

    expect(Label::query()
        ->where('organization_id', $this->organizationId)
        ->where('group', 'status')
        ->orderBy('order_col')
        ->pluck('value')
        ->all())->toBe(['active', 'pending', 'archived', 'draft']);
});

it('keeps labels with shared positions in deterministic value order', function (): void {
    app(LabelService::class)->create(LabelCreateData::fromArray([
        'labels' => [
            'status' => ['Zeta', 'Alpha'],
        ],
    ]));

    Label::query()
        ->where('organization_id', $this->organizationId)
        ->where('group', 'status')
        ->update(['order_col' => 0]);

    $this->labelableUnit->attachLabels(['status' => ['zeta', 'alpha']]);

    $expectedValues = Label::query()
        ->where('organization_id', $this->organizationId)
        ->where('group', 'status')
        ->orderBy('id')
        ->pluck('value')
        ->all();

    expect($this->labelableUnit->fresh()->labels->pluck('value')->all())
        ->toBe($expectedValues);
});

it('does not ignore non-unique database errors during label creation', function (): void {
    $missingOrganizationId = (string) Str::uuid7();
    setPermissionsTeamId($missingOrganizationId);

    expect(fn () => app(LabelService::class)->create(LabelCreateData::fromArray([
        'labels' => ['status' => ['active']],
    ])))->toThrow(QueryException::class);
});

it('validates label group keys and short label values', function (): void {
    $invalidTypeRequest = LabelCreateRequest::create('/', 'POST', [
        'labels' => [123 => ['active']],
    ])->setContainer(app())->setRedirector(app('redirect'));

    expect(fn () => $invalidTypeRequest->validateResolved())
        ->toThrow(ValidationException::class);

    $invalidNameRequest = LabelCreateRequest::create('/', 'POST', [
        'labels' => ['status' => [str_repeat('a', 51)]],
    ])->setContainer(app())->setRedirector(app('redirect'));

    expect(fn () => $invalidNameRequest->validateResolved())
        ->toThrow(ValidationException::class);

    $blankNameRequest = LabelCreateRequest::create('/', 'POST', [
        'labels' => ['status' => ['   ']],
    ])->setContainer(app())->setRedirector(app('redirect'));

    expect(fn () => $blankNameRequest->validateResolved())
        ->toThrow(ValidationException::class);
});

it('syncs only one group without wiping other types', function (): void {
    $this->labelableUnit->attachLabels([
        'status' => ['active'],
        'color'  => ['red'],
    ]);

    $this->labelableUnit->syncLabelsForGroup('status', ['inactive']);

    $grouped = $this->labelableUnit->fresh()->labels->groupBy('group');

    expect($grouped->keys()->all())->toEqualCanonicalizing(['status', 'color'])
        ->and($grouped->get('status')?->pluck('value')->all())->toEqual(['inactive'])
        ->and($grouped->get('color')?->pluck('value')->all())->toEqual(['red']);
});

it('throws when detaching unknown labels or unknown links', function (): void {
    $this->labelableUnit->attachLabels([
        'status' => ['active'],
    ]);

    expect(fn () => $this->labelableUnit->detachLabels([
        'status' => ['ghost'],
    ]))->toThrow(LabelException::class);

    $otherUnit = Unit::factory()->create([
        'organization_id' => $this->organizationId,
        'group_id'        => $this->unitGroup->id,
    ]);
    $otherLabelableUnit = TestLabelableUnit::query()->findOrFail($otherUnit->id);

    expect(fn () => $otherLabelableUnit->detachLabels([
        'status' => ['active'],
    ]))->toThrow(LabelException::class);
});

it('supports soft deleted duplicates in same tenant and keeps slug uniqueness in practice', function (): void {
    $this->labelableUnit->attachLabels([
        'status' => ['active'],
    ]);

    $originalLabel = Label::query()
        ->where('organization_id', $this->organizationId)
        ->where('group', 'status')
        ->where('value', 'active')
        ->firstOrFail();

    $this->labelableUnit->detachLabels([
        'status' => ['active'],
    ]);

    $originalLabel->delete();

    $this->labelableUnit->attachLabels([
        'status' => ['active'],
    ]);

    $replacementLabel = Label::query()
        ->where('organization_id', $this->organizationId)
        ->where('group', 'status')
        ->where('value', 'active')
        ->firstOrFail();

    expect($replacementLabel->id)->not->toBe($originalLabel->id)
        ->and($replacementLabel->slug)->not->toBe($originalLabel->slug)
        ->and(
            Label::withTrashed()
                ->where('organization_id', $this->organizationId)
                ->where('group', 'status')
                ->where('value', 'active')
                ->count()
        )->toBe(2);
});

it('rejects models without an organization_id column', function (): void {
    $currency = TestLabelableCurrency::query()->findOrFail(
        Currency::factory()->create()->id
    );

    expect(fn () => app(LabelService::class)->attach($currency, [
        'status' => ['global'],
    ]))->toThrow(LabelException::class);
});

it('rejects a labelable model from another organization when called directly', function (): void {
    $otherGroup = UnitGroup::factory()->create([
        'organization_id' => $this->otherOrganizationId,
    ]);
    $otherUnit = TestLabelableUnit::query()->findOrFail(Unit::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'group_id'        => $otherGroup->id,
    ])->id);

    expect(fn () => app(LabelService::class)->attach($otherUnit, [
        'status' => ['foreign'],
    ]))->toThrow(LabelException::class);
});

it('uses persisted organization ownership for falsified and partially hydrated models', function (): void {
    $this->labelableUnit->organization_id = $this->otherOrganizationId;

    app(LabelService::class)->attach($this->labelableUnit, [
        'status' => ['persisted-owner'],
    ]);

    $partialUnit = TestLabelableUnit::query()
        ->select(['id'])
        ->findOrFail($this->unit->id);

    app(LabelService::class)->attach($partialUnit, [
        'status' => ['partial-model'],
    ]);

    expect($this->labelableUnit->fresh()->labels->pluck('value')->all())
        ->toEqualCanonicalizing(['persisted-owner', 'partial-model']);
});

it('rejects a model with a null organization_id', function (): void {
    $systemGroup = UnitGroup::factory()->create(['organization_id' => null]);
    $systemUnit = TestLabelableUnit::query()->findOrFail(Unit::factory()->create([
        'organization_id' => null,
        'group_id'        => $systemGroup->id,
    ])->id);

    expect(fn () => app(LabelService::class)->attach($systemUnit, [
        'status' => ['global'],
    ]))->toThrow(LabelException::class);
});

it('rejects label operations without an active organization context', function (): void {
    setPermissionsTeamId(null);

    expect(fn () => app(LabelService::class)->attach($this->labelableUnit, [
        'status' => ['without-context'],
    ]))->toThrow(LabelException::class);
});

it('does not expose a foreign pivot link through the scoped labels relation', function (): void {
    $foreignLabel = Label::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'value'           => 'foreign-link',
        'group'           => 'status',
    ]);

    DB::table('master_labelables')->insert([
        'id'              => (string) Str::uuid7(),
        'organization_id' => $this->otherOrganizationId,
        'label_id'        => $foreignLabel->id,
        'labelable_type'  => $this->labelableUnit->getMorphClass(),
        'labelable_id'    => $this->labelableUnit->id,
    ]);

    expect($this->labelableUnit->fresh()->labels->pluck('value')->all())
        ->not->toContain('foreign-link');
});

it('rejects label operations on models without the labels trait', function (): void {
    $label = Label::factory()->create([
        'organization_id' => $this->organizationId,
    ]);
    /** @var mixed $invalidModel */
    $invalidModel = $label;

    expect(fn () => app(LabelService::class)->attach($invalidModel, [
        'status' => ['active'],
    ]))->toThrow(LabelException::class);
});

it('updates a label value without changing its slug', function (): void {
    $label = Label::factory()->create([
        'organization_id' => $this->organizationId,
        'value'           => 'Original value',
        'group'           => 'status',
    ]);
    $slug = $label->slug;

    app(LabelService::class)->update($label, LabelUpdateData::fromArray(['value' => 'Renamed label']));

    expect($label->fresh()->value)->toBe('renamed label')
        ->and($label->fresh()->slug)->toBe($slug)
        ->and($label->fresh()->group)->toBe('status');
});

it('lists only current organization labels with filters', function (): void {
    Label::factory()->create([
        'organization_id' => $this->organizationId,
        'value'           => 'Alpha',
        'group'           => 'status',
    ]);
    Label::factory()->create([
        'organization_id' => $this->organizationId,
        'value'           => 'Beta',
        'group'           => 'color',
    ]);
    Label::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'value'           => 'Alpha foreign',
        'group'           => 'status',
    ]);

    $collection = app(LabelService::class)->paginate(LabelFilterData::fromArray([
        'value' => 'al',
    ]));

    expect(collect($collection->items())->pluck('value')->all())->toBe(['alpha']);
});

it('reorders all labels of one group and rejects incomplete lists', function (): void {
    $labels = Label::factory()->count(3)->create([
        'organization_id' => $this->organizationId,
        'group'           => 'status',
    ]);
    $orderedIds = $labels->pluck('id')->reverse()->values()->all();

    app(LabelService::class)->reorder(LabelReorderData::fromArray([
        'group'     => 'status',
        'label_ids' => $orderedIds,
    ]));

    expect(Label::query()->whereIn('id', $orderedIds)->orderBy('order_col')->pluck('id')->all())
        ->toBe($orderedIds);

    expect(fn () => app(LabelService::class)->reorder(LabelReorderData::fromArray([
        'group'     => 'status',
        'label_ids' => array_slice($orderedIds, 1),
    ])))->toThrow(LabelException::class);
});

it('blocks deletion of a label that is still used', function (): void {
    $label = Label::factory()->create([
        'organization_id' => $this->organizationId,
        'group'           => 'status',
    ]);
    DB::table('master_labelables')->insert([
        'id'              => (string) Str::uuid7(),
        'organization_id' => $this->organizationId,
        'label_id'        => $label->id,
        'labelable_type'  => TestLabelableUnit::class,
        'labelable_id'    => $this->labelableUnit->id,
    ]);

    try {
        app(LabelService::class)->delete($label);
        expect(false)->toBeTrue();
    } catch (LabelException $exception) {
        expect($exception->context()['usages'])->toHaveCount(1);
    }
});

it('deletes an unused label', function (): void {
    $label = Label::factory()->create(['organization_id' => $this->organizationId]);

    app(LabelService::class)->delete($label);

    expect(Label::withTrashed()->find($label->id)?->trashed())->toBeTrue();
});

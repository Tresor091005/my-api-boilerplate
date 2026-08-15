<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Master\Data\TagCreateData;
use Lahatre\Master\Data\TagFilterData;
use Lahatre\Master\Data\TagReorderData;
use Lahatre\Master\Data\TagUpdateData;
use Lahatre\Master\Exceptions\TagException;
use Lahatre\Master\Http\Requests\TagCreateRequest;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Tag;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Services\TagService;
use Lahatre\Master\Tests\Concerns\InteractsWithMasterTenantContext;
use Lahatre\Master\Tests\Support\Models\TestTaggableCurrency;
use Lahatre\Master\Tests\Support\Models\TestTaggableUnit;
use TypeError;

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

    $this->taggableUnit = TestTaggableUnit::query()->findOrFail($this->unit->id);
});

it('attaches tags by type, normalizes values, and avoids duplicates', function (): void {
    $this->taggableUnit->attachTags([
        'Status' => [' Active ', 'active'],
        'Color'  => ['Red', 'Blue'],
    ]);

    $this->taggableUnit->attachTags([
        'status' => ['active'],
        'color'  => ['red'],
    ]);

    $tags = $this->taggableUnit->tags()->get();

    expect($tags)->toHaveCount(3)
        ->and($tags->pluck('type')->all())->toEqualCanonicalizing(['status', 'color', 'color'])
        ->and($tags->pluck('name')->all())->toEqualCanonicalizing(['active', 'red', 'blue']);

    expect(
        Tag::query()
            ->where('organization_id', $this->organizationId)
            ->count()
    )->toBe(3)
        ->and($this->taggableUnit->tags()->count())->toBe(3);
});

it('creates tags in batches by type and ignores unique conflicts', function (): void {
    $data = TagCreateData::fromArray([
        'tags' => [
            'Status' => ['Active', 'Inactive'],
            'Color'  => ['Red'],
        ],
    ]);

    $firstResult = app(TagService::class)->create($data);
    $secondResult = app(TagService::class)->create($data);

    expect($firstResult)->toHaveCount(3)
        ->and($secondResult)->toHaveCount(3)
        ->and(Tag::query()->where('organization_id', $this->organizationId)->count())->toBe(3);
});

it('does not ignore non-unique database errors during tag creation', function (): void {
    $missingOrganizationId = (string) Str::uuid7();
    setPermissionsTeamId($missingOrganizationId);

    expect(fn () => app(TagService::class)->create(TagCreateData::fromArray([
        'tags' => ['status' => ['active']],
    ])))->toThrow(QueryException::class);
});

it('validates tag type keys and short tag names', function (): void {
    $invalidTypeRequest = TagCreateRequest::create('/', 'POST', [
        'tags' => [123 => ['active']],
    ])->setContainer(app())->setRedirector(app('redirect'));

    expect(fn () => $invalidTypeRequest->validateResolved())
        ->toThrow(ValidationException::class);

    $invalidNameRequest = TagCreateRequest::create('/', 'POST', [
        'tags' => ['status' => [str_repeat('a', 51)]],
    ])->setContainer(app())->setRedirector(app('redirect'));

    expect(fn () => $invalidNameRequest->validateResolved())
        ->toThrow(ValidationException::class);

    $blankNameRequest = TagCreateRequest::create('/', 'POST', [
        'tags' => ['status' => ['   ']],
    ])->setContainer(app())->setRedirector(app('redirect'));

    expect(fn () => $blankNameRequest->validateResolved())
        ->toThrow(ValidationException::class);
});

it('syncs only one type without wiping other types', function (): void {
    $this->taggableUnit->attachTags([
        'status' => ['active'],
        'color'  => ['red'],
    ]);

    $this->taggableUnit->syncTagsForType('status', ['inactive']);

    $grouped = $this->taggableUnit->fresh()->tags->groupBy('type');

    expect($grouped->keys()->all())->toEqualCanonicalizing(['status', 'color'])
        ->and($grouped->get('status')?->pluck('name')->all())->toEqual(['inactive'])
        ->and($grouped->get('color')?->pluck('name')->all())->toEqual(['red']);
});

it('throws when detaching unknown tags or unknown links', function (): void {
    $this->taggableUnit->attachTags([
        'status' => ['active'],
    ]);

    expect(fn () => $this->taggableUnit->detachTags([
        'status' => ['ghost'],
    ]))->toThrow(TagException::class);

    $otherUnit = Unit::factory()->create([
        'organization_id' => $this->organizationId,
        'group_id'        => $this->unitGroup->id,
    ]);
    $otherTaggableUnit = TestTaggableUnit::query()->findOrFail($otherUnit->id);

    expect(fn () => $otherTaggableUnit->detachTags([
        'status' => ['active'],
    ]))->toThrow(TagException::class);
});

it('supports soft deleted duplicates in same tenant and keeps slug uniqueness in practice', function (): void {
    $this->taggableUnit->attachTags([
        'status' => ['active'],
    ]);

    $originalTag = Tag::query()
        ->where('organization_id', $this->organizationId)
        ->where('type', 'status')
        ->where('name', 'active')
        ->firstOrFail();

    $this->taggableUnit->detachTags([
        'status' => ['active'],
    ]);

    $originalTag->delete();

    $this->taggableUnit->attachTags([
        'status' => ['active'],
    ]);

    $replacementTag = Tag::query()
        ->where('organization_id', $this->organizationId)
        ->where('type', 'status')
        ->where('name', 'active')
        ->firstOrFail();

    expect($replacementTag->id)->not->toBe($originalTag->id)
        ->and($replacementTag->slug)->not->toBe($originalTag->slug)
        ->and(
            Tag::withTrashed()
                ->where('organization_id', $this->organizationId)
                ->where('type', 'status')
                ->where('name', 'active')
                ->count()
        )->toBe(2);
});

it('rejects models without an organization_id column', function (): void {
    $currency = TestTaggableCurrency::query()->findOrFail(
        Currency::factory()->create()->id
    );

    expect(fn () => app(TagService::class)->attach($currency, [
        'status' => ['global'],
    ]))->toThrow(TagException::class);
});

it('rejects a taggable model from another organization when called directly', function (): void {
    $otherGroup = UnitGroup::factory()->create([
        'organization_id' => $this->otherOrganizationId,
    ]);
    $otherUnit = TestTaggableUnit::query()->findOrFail(Unit::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'group_id'        => $otherGroup->id,
    ])->id);

    expect(fn () => app(TagService::class)->attach($otherUnit, [
        'status' => ['foreign'],
    ]))->toThrow(TagException::class);
});

it('uses persisted organization ownership for falsified and partially hydrated models', function (): void {
    $this->taggableUnit->organization_id = $this->otherOrganizationId;

    app(TagService::class)->attach($this->taggableUnit, [
        'status' => ['persisted-owner'],
    ]);

    $partialUnit = TestTaggableUnit::query()
        ->select(['id'])
        ->findOrFail($this->unit->id);

    app(TagService::class)->attach($partialUnit, [
        'status' => ['partial-model'],
    ]);

    expect($this->taggableUnit->fresh()->tags->pluck('name')->all())
        ->toEqualCanonicalizing(['persisted-owner', 'partial-model']);
});

it('rejects a model with a null organization_id', function (): void {
    $systemGroup = UnitGroup::factory()->create(['organization_id' => null]);
    $systemUnit = TestTaggableUnit::query()->findOrFail(Unit::factory()->create([
        'organization_id' => null,
        'group_id'        => $systemGroup->id,
    ])->id);

    expect(fn () => app(TagService::class)->attach($systemUnit, [
        'status' => ['global'],
    ]))->toThrow(TagException::class);
});

it('rejects tag operations without an active organization context', function (): void {
    setPermissionsTeamId(null);

    expect(fn () => app(TagService::class)->attach($this->taggableUnit, [
        'status' => ['without-context'],
    ]))->toThrow(TagException::class);
});

it('does not expose a foreign pivot link through the scoped tags relation', function (): void {
    $foreignTag = Tag::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'name'            => 'foreign-link',
        'type'            => 'status',
    ]);

    DB::table('master_taggables')->insert([
        'id'              => (string) Str::uuid7(),
        'organization_id' => $this->otherOrganizationId,
        'tag_id'          => $foreignTag->id,
        'taggable_type'   => $this->taggableUnit->getMorphClass(),
        'taggable_id'     => $this->taggableUnit->id,
    ]);

    expect($this->taggableUnit->fresh()->tags->pluck('name')->all())
        ->not->toContain('foreign-link');
});

it('rejects tag operations on models without the has tags contract', function (): void {
    $tag = Tag::factory()->create([
        'organization_id' => $this->organizationId,
    ]);

    expect(fn () => app(TagService::class)->attach($tag, [
        'status' => ['active'],
    ]))->toThrow(TypeError::class);
});

it('updates a tag name without changing its slug', function (): void {
    $tag = Tag::factory()->create([
        'organization_id' => $this->organizationId,
        'name'            => 'Original name',
        'type'            => 'status',
    ]);
    $slug = $tag->slug;

    app(TagService::class)->update($tag, TagUpdateData::fromArray(['name' => 'Renamed tag']));

    expect($tag->fresh()->name)->toBe('renamed tag')
        ->and($tag->fresh()->slug)->toBe($slug)
        ->and($tag->fresh()->type)->toBe('status');
});

it('lists only current organization tags with filters', function (): void {
    Tag::factory()->create([
        'organization_id' => $this->organizationId,
        'name'            => 'Alpha',
        'type'            => 'status',
    ]);
    Tag::factory()->create([
        'organization_id' => $this->organizationId,
        'name'            => 'Beta',
        'type'            => 'color',
    ]);
    Tag::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'name'            => 'Alpha foreign',
        'type'            => 'status',
    ]);

    $collection = app(TagService::class)->paginate(TagFilterData::fromArray([
        'name' => 'al',
    ]));

    expect(collect($collection->items())->pluck('name')->all())->toBe(['alpha']);
});

it('reorders all tags of one type and rejects incomplete lists', function (): void {
    $tags = Tag::factory()->count(3)->create([
        'organization_id' => $this->organizationId,
        'type'            => 'status',
    ]);
    $orderedIds = $tags->pluck('id')->reverse()->values()->all();

    app(TagService::class)->reorder(TagReorderData::fromArray([
        'type'    => 'status',
        'tag_ids' => $orderedIds,
    ]));

    expect(Tag::query()->whereIn('id', $orderedIds)->orderBy('order_col')->pluck('id')->all())
        ->toBe($orderedIds);

    expect(fn () => app(TagService::class)->reorder(TagReorderData::fromArray([
        'type'    => 'status',
        'tag_ids' => array_slice($orderedIds, 1),
    ])))->toThrow(TagException::class);
});

it('blocks deletion of a tag that is still used', function (): void {
    $tag = Tag::factory()->create([
        'organization_id' => $this->organizationId,
        'type'            => 'status',
    ]);
    DB::table('master_taggables')->insert([
        'id'              => (string) Str::uuid7(),
        'organization_id' => $this->organizationId,
        'tag_id'          => $tag->id,
        'taggable_type'   => TestTaggableUnit::class,
        'taggable_id'     => $this->taggableUnit->id,
    ]);

    try {
        app(TagService::class)->delete($tag);
        expect(false)->toBeTrue();
    } catch (TagException $exception) {
        expect($exception->context()['usages'])->toHaveCount(1);
    }
});

it('deletes an unused tag', function (): void {
    $tag = Tag::factory()->create(['organization_id' => $this->organizationId]);

    app(TagService::class)->delete($tag);

    expect(Tag::withTrashed()->find($tag->id)?->trashed())->toBeTrue();
});

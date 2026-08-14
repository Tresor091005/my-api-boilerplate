<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Exceptions\TagException;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Tag;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Services\Tag\TagService;
use Lahatre\Master\Tests\Concerns\InteractsWithMasterTenantContext;
use Lahatre\Master\Tests\Support\Models\TestTaggableCurrency;
use Lahatre\Master\Tests\Support\Models\TestTaggableUnit;

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
    $this->taggableUnit->attach([
        'Status' => [' Active ', 'active'],
        'Color'  => ['Red', 'Blue'],
    ]);

    $tags = $this->taggableUnit->tags()->get();

    expect($tags)->toHaveCount(3)
        ->and($tags->pluck('type')->all())->toEqualCanonicalizing(['status', 'color', 'color'])
        ->and($tags->pluck('name')->all())->toEqualCanonicalizing(['active', 'red', 'blue']);

    expect(
        Tag::query()
            ->where('organization_id', $this->organizationId)
            ->count()
    )->toBe(3);
});

it('syncs only one type without wiping other types', function (): void {
    $this->taggableUnit->attach([
        'status' => ['active'],
        'color'  => ['red'],
    ]);

    $this->taggableUnit->syncForType('status', ['inactive']);

    $grouped = $this->taggableUnit->fresh()->tags->groupBy('type');

    expect($grouped->keys()->all())->toEqualCanonicalizing(['status', 'color'])
        ->and($grouped->get('status')?->pluck('name')->all())->toEqual(['inactive'])
        ->and($grouped->get('color')?->pluck('name')->all())->toEqual(['red']);
});

it('throws when detaching unknown tags or unknown links', function (): void {
    $this->taggableUnit->attach([
        'status' => ['active'],
    ]);

    expect(fn () => $this->taggableUnit->detach([
        'status' => ['ghost'],
    ]))->toThrow(TagException::class);

    $otherUnit = Unit::factory()->create([
        'organization_id' => $this->organizationId,
        'group_id'        => $this->unitGroup->id,
    ]);
    $otherTaggableUnit = TestTaggableUnit::query()->findOrFail($otherUnit->id);

    expect(fn () => $otherTaggableUnit->detach([
        'status' => ['active'],
    ]))->toThrow(TagException::class);
});

it('supports soft deleted duplicates in same tenant and keeps slug uniqueness in practice', function (): void {
    $this->taggableUnit->attach([
        'status' => ['active'],
    ]);

    $originalTag = Tag::query()
        ->where('organization_id', $this->organizationId)
        ->where('type', 'status')
        ->where('name', 'active')
        ->firstOrFail();

    $this->taggableUnit->detach([
        'status' => ['active'],
    ]);

    $originalTag->delete();

    $this->taggableUnit->attach([
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

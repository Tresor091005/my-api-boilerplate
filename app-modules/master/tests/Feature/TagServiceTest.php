<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Master\Exceptions\Tag\ModelMissingHasTagsTraitException;
use Lahatre\Master\Exceptions\Tag\TagLinkNotFoundException;
use Lahatre\Master\Exceptions\Tag\TagNotFoundException;
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
    ]))->toThrow(TagNotFoundException::class);

    $otherUnit = Unit::factory()->create([
        'organization_id' => $this->organizationId,
        'group_id'        => $this->unitGroup->id,
    ]);
    $otherTaggableUnit = TestTaggableUnit::query()->findOrFail($otherUnit->id);

    expect(fn () => $otherTaggableUnit->detach([
        'status' => ['active'],
    ]))->toThrow(TagLinkNotFoundException::class);
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

it('scopes tags by tenancy for models without organization_id using current team context', function (): void {
    $currencyOne = TestTaggableCurrency::query()->findOrFail(Currency::factory()->create()->id);
    $currencyTwo = TestTaggableCurrency::query()->findOrFail(Currency::factory()->create()->id);

    setPermissionsTeamId($this->organizationId);
    $currencyOne->attach([
        'status' => ['shared'],
    ]);

    setPermissionsTeamId($this->otherOrganizationId);
    $currencyTwo->attach([
        'status' => ['shared'],
    ]);

    $firstTenantTag = Tag::query()
        ->where('organization_id', $this->organizationId)
        ->where('type', 'status')
        ->where('name', 'shared')
        ->first();

    $otherTenantTag = Tag::query()
        ->where('organization_id', $this->otherOrganizationId)
        ->where('type', 'status')
        ->where('name', 'shared')
        ->first();

    expect($firstTenantTag)->not->toBeNull()
        ->and($otherTenantTag)->not->toBeNull()
        ->and($firstTenantTag?->id)->not->toBe($otherTenantTag?->id);
});

it('rejects tag operations on models without has tags trait', function (): void {
    $tag = Tag::factory()->create([
        'organization_id' => $this->organizationId,
    ]);

    expect(fn () => app(TagService::class)->attach($tag, [
        'status' => ['active'],
    ]))->toThrow(ModelMissingHasTagsTraitException::class);
});

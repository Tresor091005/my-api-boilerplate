<?php

declare(strict_types=1);

namespace Lahatre\Master\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Lahatre\Master\Contracts\HasTags as HasTagsContract;
use Lahatre\Master\Models\Tag;
use Lahatre\Master\Services\TagService;

/**
 * @phpstan-require-implements HasTagsContract
 *
 * @mixin Model
 */
trait InteractsWithTags
{
    /**
     * @param  array<int, string>  $tags
     */
    public function scopeWithAnyTagsOfType(Builder $query, string $type, array $tags): Builder
    {
        $normalizedType = $this->normalizeTagType($type);
        $normalizedTags = $this->normalizeTagNames($tags);
        if ($normalizedTags->isEmpty()) {
            return $query;
        }

        $query = $this->scopeToTagOrganization($query);

        return $query->whereHas('tags', function (Builder $tagQuery) use ($normalizedType, $normalizedTags): void {
            $tagQuery->where('master_tags.type', $normalizedType);
            $tagQuery->whereIn('master_tags.name', $normalizedTags);
        });
    }

    /**
     * @param  array<int, string>  $tags
     */
    public function scopeWithAllTagsOfType(Builder $query, string $type, array $tags): Builder
    {
        $normalizedType = $this->normalizeTagType($type);
        $normalizedTags = $this->normalizeTagNames($tags);
        if ($normalizedTags->isEmpty()) {
            return $query;
        }

        $query = $this->scopeToTagOrganization($query);

        return $query->whereHas(
            'tags',
            function (Builder $tagQuery) use ($normalizedType, $normalizedTags): void {
                $tagQuery->where('master_tags.type', $normalizedType);
                $tagQuery->whereIn('master_tags.name', $normalizedTags);
            },
            '>=',
            $normalizedTags->count()
        );
    }

    /**
     * @param  array<int, string>  $tags
     */
    public function scopeWithoutTagsOfType(Builder $query, string $type, array $tags): Builder
    {
        $normalizedType = $this->normalizeTagType($type);
        $normalizedTags = $this->normalizeTagNames($tags);
        if ($normalizedTags->isEmpty()) {
            return $query;
        }

        $query = $this->scopeToTagOrganization($query);

        return $query->whereDoesntHave('tags', function (Builder $tagQuery) use ($normalizedType, $normalizedTags): void {
            $tagQuery->where('master_tags.type', $normalizedType);
            $tagQuery->whereIn('master_tags.name', $normalizedTags);
        });
    }

    public function tags(): MorphToMany
    {
        $organizationId = currentOrganizationId();

        return $this->morphToMany(Tag::class, 'taggable', 'master_taggables', 'taggable_id', 'tag_id')
            ->wherePivot('organization_id', $organizationId)
            ->where('master_tags.organization_id', $organizationId)
            ->orderBy('master_tags.type')
            ->orderBy('master_tags.order_col');
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     */
    public function attachTags(array $tagsByType): void
    {
        app(TagService::class)->attach($this, $tagsByType);
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $tags
     */
    public function attachTagsForType(string $type, Collection|array $tags): void
    {
        $this->attachTags([$type => $tags]);
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     */
    public function detachTags(array $tagsByType): void
    {
        app(TagService::class)->detach($this, $tagsByType);
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $tags
     */
    public function detachTagsForType(string $type, Collection|array $tags): void
    {
        $this->detachTags([$type => $tags]);
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     */
    public function syncTags(array $tagsByType): void
    {
        app(TagService::class)->sync($this, $tagsByType);
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $tags
     */
    public function syncTagsForType(string $type, Collection|array $tags): void
    {
        app(TagService::class)->syncForType($this, $type, $tags);
    }

    /**
     * @param  array<int, string>  $tags
     * @return Collection<int, string>
     */
    protected function normalizeTagNames(array $tags): Collection
    {
        /** @var Collection<int, string> $normalized */
        $normalized = collect($tags)
            ->map(fn (string $tag): string => str($tag)->normalize()->value())
            ->filter(fn (string $tag): bool => $tag !== '')
            ->unique()
            ->values();

        return $normalized;
    }

    protected function normalizeTagType(string $type): string
    {
        return str($type)->normalize()->value();
    }

    protected function scopeToTagOrganization(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('organization_id'), currentOrganizationId());
    }
}

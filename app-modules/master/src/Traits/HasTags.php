<?php

declare(strict_types=1);

namespace Lahatre\Master\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Master\Models\Tag;
use Lahatre\Master\Services\Tag\TagService;

/**
 * @mixin Model
 */
trait HasTags
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

        return $query->whereDoesntHave('tags', function (Builder $tagQuery) use ($normalizedType, $normalizedTags): void {
            $tagQuery->where('master_tags.type', $normalizedType);
            $tagQuery->whereIn('master_tags.name', $normalizedTags);
        });
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'master_taggables', 'taggable_id', 'tag_id')
            ->orderBy('master_tags.type')
            ->orderBy('master_tags.order_col');
    }

    public function tagsOfType(string $type): MorphToMany
    {
        return $this->tags()->where('master_tags.type', str($type)->normalize()->value());
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     */
    public function attach(array $tagsByType): static
    {
        DB::transaction(function () use ($tagsByType): void {
            app(TagService::class)->attach($this, $tagsByType);
        });

        return $this->load('tags');
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $tags
     */
    public function attachForType(string $type, Collection|array $tags): static
    {
        return $this->attach([$type => $tags]);
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     */
    public function detach(array $tagsByType): static
    {
        DB::transaction(function () use ($tagsByType): void {
            app(TagService::class)->detach($this, $tagsByType);
        });

        return $this->load('tags');
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $tags
     */
    public function detachForType(string $type, Collection|array $tags): static
    {
        return $this->detach([$type => $tags]);
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     */
    public function sync(array $tagsByType): static
    {
        DB::transaction(function () use ($tagsByType): void {
            app(TagService::class)->sync($this, $tagsByType);
        });

        return $this->load('tags');
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $tags
     */
    public function syncForType(string $type, Collection|array $tags): static
    {
        DB::transaction(function () use ($type, $tags): void {
            app(TagService::class)->syncForType($this, $type, $tags);
        });

        return $this->load('tags');
    }

    /**
     * @param  array<int, string>  $tags
     * @return Collection<int, string>
     */
    protected function normalizeTagNames(array $tags): Collection
    {
        return collect($tags)
            ->filter(fn (mixed $tag): bool => is_string($tag))
            ->map(fn (string $tag): string => str($tag)->normalize()->value())
            ->filter(fn (string $tag): bool => $tag !== '')
            ->unique()
            ->values();
    }

    protected function normalizeTagType(string $type): string
    {
        return str($type)->normalize()->value();
    }
}

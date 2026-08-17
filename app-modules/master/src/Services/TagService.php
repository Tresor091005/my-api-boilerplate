<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lahatre\Master\Contracts\HasTags as HasTagsContract;
use Lahatre\Master\Data\TagCreateData;
use Lahatre\Master\Data\TagFilterData;
use Lahatre\Master\Data\TagReorderData;
use Lahatre\Master\Data\TagUpdateData;
use Lahatre\Master\Exceptions\TagException;
use Lahatre\Master\Models\Tag;
use Lahatre\Master\Models\Taggable;
use Lahatre\Master\Traits\InteractsWithTags;
use Lahatre\Shared\Support\HandleGenerator;

class TagService
{
    public function paginate(TagFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate($this->tagsQuery($filters), $filters);
    }

    /** @return Builder<Tag> */
    private function tagsQuery(TagFilterData $filters): Builder
    {
        $query = Tag::query()->where('organization_id', currentOrganizationId());

        if ($filters->name) {
            $query->where('name', 'like', str($filters->name)->normalize()->value().'%');
        }

        if ($filters->type) {
            $query->where('type', str($filters->type)->normalize()->value());
        }

        $sortColumn = match ($filters->sortBy) {
            'name',
            'type',
            'order_col',
            'created_at',
            'updated_at' => $filters->sortBy,
            default      => 'name',
        };

        $query->orderBy($sortColumn, $filters->sortOrder);

        return $query;
    }

    public function create(TagCreateData $data): Collection
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(fn (): Collection => $this->ensureTagsExist($organizationId, $this->normalizeTagsByType($data->tagsByType)));
    }

    public function update(Tag $tag, TagUpdateData $data): Tag
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($tag, $data, $organizationId): Tag {
            $ownedTag = Tag::query()
                ->where('organization_id', $organizationId)
                ->whereKey($tag->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $ownedTag->name = $data->name;
            $ownedTag->save();

            return $ownedTag->fresh();
        });
    }

    public function reorder(TagReorderData $data): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($data, $organizationId): void {
            /** @var Collection<int, Tag> $tags */
            $tags = Tag::query()
                ->where('organization_id', $organizationId)
                ->where('type', $data->type)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $expectedTagIds = $tags->pluck('id')->all();
            $missingTagIds = array_values(array_diff($expectedTagIds, $data->tagIds));
            $unexpectedTagIds = array_values(array_diff($data->tagIds, $expectedTagIds));

            if ($missingTagIds !== [] || $unexpectedTagIds !== []) {
                throw TagException::reorderMismatch($missingTagIds, $unexpectedTagIds);
            }

            $case = collect($data->tagIds)
                ->map(fn (string $tagId): string => 'WHEN ? THEN ?::integer')
                ->implode(' ');
            $placeholders = implode(',', array_fill(0, count($data->tagIds), '?'));
            $bindings = [];

            foreach ($data->tagIds as $order => $tagId) {
                $bindings[] = $tagId;
                $bindings[] = $order;
            }

            $bindings[] = now();
            $bindings[] = $organizationId;
            array_push($bindings, ...$data->tagIds);

            DB::update(
                "UPDATE master_tags SET order_col = CASE id {$case} END, updated_at = ? WHERE organization_id = ? AND deleted_at IS NULL AND id IN ({$placeholders})",
                $bindings,
            );
        });
    }

    public function delete(Tag $tag): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($tag, $organizationId): void {
            $ownedTag = Tag::query()
                ->where('organization_id', $organizationId)
                ->whereKey($tag->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $usages = Taggable::query()
                ->where('organization_id', $organizationId)
                ->where('tag_id', $ownedTag->getKey())
                ->get(['taggable_type', 'taggable_id'])
                ->map(fn (Taggable $taggable): array => [
                    'taggable_type' => $taggable->taggable_type,
                    'taggable_id'   => $taggable->taggable_id,
                ])
                ->all();

            if ($usages !== []) {
                throw TagException::inUse($usages);
            }

            $ownedTag->delete();
        });
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     */
    public function attach(HasTagsContract $model, array $tagsByType): void
    {
        DB::transaction(function () use ($model, $tagsByType): void {
            $this->assertModelUsesTags($model);
            $organizationId = $this->resolveAndValidateOrganizationId($model);
            $this->lockTaggable($model, $organizationId);
            $normalizedTagsByType = $this->normalizeTagsByType($tagsByType);

            if ($normalizedTagsByType->isEmpty()) {
                return;
            }

            $tags = $this->ensureTagsExist($organizationId, $normalizedTagsByType);

            $tagIds = $tags->pluck('id')->unique()->values()->all();
            if ($tagIds !== []) {
                $syncPayload = collect($tagIds)
                    ->mapWithKeys(fn (string $tagId): array => [
                        $tagId => [
                            'id'              => (string) Str::uuid7(),
                            'organization_id' => $organizationId,
                        ],
                    ])
                    ->all();

                if ($syncPayload !== []) {
                    $model->tags()->syncWithoutDetaching($syncPayload);
                }
            }
        });
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     */
    public function detach(HasTagsContract $model, array $tagsByType): void
    {
        DB::transaction(function () use ($model, $tagsByType): void {
            $this->assertModelUsesTags($model);
            $organizationId = $this->resolveAndValidateOrganizationId($model);
            $this->lockTaggable($model, $organizationId);
            $normalizedTagsByType = $this->normalizeTagsByType($tagsByType);

            foreach ($normalizedTagsByType as $type => $names) {
                $tags = Tag::query()
                    ->where('organization_id', $organizationId)
                    ->where('type', $type)
                    ->whereIn('name', $names)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('name');

                $missingNames = $names->reject(fn (string $name): bool => $tags->has($name))->values();
                if ($missingNames->isNotEmpty()) {
                    throw TagException::notFound($type, $missingNames->all());
                }

                $tagIds = $tags->pluck('id');
                $linkedTagIds = $model->tags()
                    ->where('master_tags.type', $type)
                    ->whereIn('master_tags.id', $tagIds)
                    ->pluck('master_tags.id');

                $missingLinkedNames = $tags
                    ->reject(fn (Tag $tag): bool => $linkedTagIds->contains($tag->id))
                    ->pluck('name')
                    ->values();

                if ($missingLinkedNames->isNotEmpty()) {
                    throw TagException::linkNotFound($type, $missingLinkedNames->all());
                }

                $model->tags()->detach($tagIds->all());
            }
        });
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     */
    public function sync(HasTagsContract $model, array $tagsByType): void
    {
        DB::transaction(function () use ($model, $tagsByType): void {
            $this->assertModelUsesTags($model);
            $organizationId = $this->resolveAndValidateOrganizationId($model);
            $this->lockTaggable($model, $organizationId);
            $model->tags()->detach();
            $this->attach($model, $tagsByType);
        });
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $tags
     */
    public function syncForType(HasTagsContract $model, string $type, Collection|array $tags): void
    {
        DB::transaction(function () use ($model, $tags, $type): void {
            $this->assertModelUsesTags($model);
            $organizationId = $this->resolveAndValidateOrganizationId($model);
            $this->lockTaggable($model, $organizationId);
            $normalizedType = str($type)->normalize()->value();
            $existingTagIds = $model->tags()
                ->where('master_tags.type', $normalizedType)
                ->pluck('master_tags.id')
                ->all();

            if ($existingTagIds !== []) {
                $model->tags()->detach($existingTagIds);
            }

            $this->attach($model, [$normalizedType => $tags]);
        });
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     * @return Collection<string, Collection<int, string>>
     */
    protected function normalizeTagsByType(array $tagsByType): Collection
    {
        /** @var Collection<string, Collection<int, string>> $normalized */
        $normalized = collect($tagsByType)
            ->mapWithKeys(function (mixed $tags, mixed $type): array {
                $normalizedType = str((string) $type)->normalize()->value();
                /** @var Collection<int, string> $normalizedTags */
                $normalizedTags = collect($tags)
                    ->filter(fn (mixed $tag): bool => is_string($tag))
                    ->map(fn (string $tag): string => str($tag)->normalize()->value())
                    ->filter(fn (string $tag): bool => $tag !== '')
                    ->unique()
                    ->values();

                return [$normalizedType => $normalizedTags];
            })
            ->filter(fn (Collection $tags): bool => $tags->isNotEmpty());

        return $normalized;
    }

    /**
     * @param  Collection<string, Collection<int, string>>  $normalizedTagsByType
     * @return Collection<int, Tag>
     */
    protected function ensureTagsExist(string $organizationId, Collection $normalizedTagsByType): Collection
    {
        /** @var Collection<int, Tag> $tags */
        $tags = collect();
        $now = now();

        foreach ($normalizedTagsByType->sortKeys() as $type => $names) {
            $rows = $names->map(fn (string $name): array => [
                'id'              => (string) Str::uuid7(),
                'organization_id' => $organizationId,
                'name'            => $name,
                'slug'            => HandleGenerator::generate(
                    name: $name,
                    table: 'master_tags',
                    column: 'slug',
                    extra: ['organization_id' => $organizationId],
                ),
                'type'       => $type,
                'order_col'  => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            Tag::query()->insertOrIgnore($rows);

            $tags = $tags->merge(
                Tag::query()
                    ->where('organization_id', $organizationId)
                    ->where('type', $type)
                    ->whereIn('name', $names)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
            );
        }

        return $tags;
    }

    protected function lockTaggable(HasTagsContract $model, string $organizationId): void
    {
        $model->newQuery()
            ->where('organization_id', $organizationId)
            ->whereKey($model->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function resolveAndValidateOrganizationId(HasTagsContract $model): string
    {
        if (!Schema::hasColumn($model->getTable(), 'organization_id')) {
            throw TagException::organizationResolutionFailed();
        }

        try {
            $organizationId = currentOrganizationId();
        } catch (AuthorizationException) {
            throw TagException::organizationResolutionFailed();
        }

        if ($model->getKey() === null) {
            throw TagException::organizationResolutionFailed();
        }

        $persistedAttributes = $model->newQuery()
            ->whereKey($model->getKey())
            ->firstOrFail()
            ->getAttributes();
        /** Rehydrate a typed clone so the contract getter reads persisted attributes. */
        $persistedModel = clone $model;
        $persistedModel->setRawAttributes($persistedAttributes, true);
        $persistedOrganizationId = $persistedModel->getOrganizationId();

        if ($persistedOrganizationId === '') {
            throw TagException::organizationResolutionFailed();
        }

        if ($persistedOrganizationId !== $organizationId) {
            throw TagException::organizationMismatch($organizationId, $persistedOrganizationId);
        }

        return $organizationId;
    }

    protected function assertModelUsesTags(HasTagsContract $model): void
    {
        if (!in_array(InteractsWithTags::class, class_uses_recursive($model::class), true)) {
            throw TagException::modelMissingInteractsWithTagsTrait($model::class);
        }
    }
}

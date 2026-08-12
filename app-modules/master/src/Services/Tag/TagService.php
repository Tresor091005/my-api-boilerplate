<?php

declare(strict_types=1);

namespace Lahatre\Master\Services\Tag;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lahatre\Master\Exceptions\TagException;
use Lahatre\Master\Models\Tag;
use Lahatre\Master\Traits\HasTags;
use Lahatre\Shared\Support\HandleGenerator;

class TagService
{
    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     * @return EloquentCollection<int, Tag>
     */
    public function attach(Model $model, array $tagsByType): EloquentCollection
    {
        $this->assertModelUsesTags($model);
        $organizationId = $this->resolveOrganizationId($model);
        $normalizedTagsByType = $this->normalizeTagsByType($tagsByType);

        if ($normalizedTagsByType->isEmpty()) {
            return new EloquentCollection();
        }

        /** @var Collection<int, Tag> $tags */
        $tags = collect();
        $now = now();

        foreach ($normalizedTagsByType as $type => $names) {
            $existingTags = Tag::query()
                ->where('organization_id', $organizationId)
                ->where('type', $type)
                ->whereIn('name', $names)
                ->get()
                ->keyBy('name');

            $missingNames = $names->reject(fn (string $name): bool => $existingTags->has($name));
            if ($missingNames->isNotEmpty()) {
                $missingRows = $missingNames->map(fn (string $name): array => [
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

                Tag::query()->insert($missingRows);

                $createdTags = Tag::query()
                    ->where('organization_id', $organizationId)
                    ->where('type', $type)
                    ->whereIn('name', $missingNames)
                    ->get();

                $tags = $tags->merge($createdTags);
            }

            $tags = $tags->merge($existingTags->values());
        }

        $tagIds = $tags->pluck('id')->unique()->values()->all();
        if ($tagIds !== []) {
            $alreadyLinkedTagIds = $this->tagsRelation($model)
                ->whereIn('master_tags.id', $tagIds)
                ->pluck('master_tags.id')
                ->all();

            $tagIdsToAttach = array_values(array_diff($tagIds, $alreadyLinkedTagIds));

            $syncPayload = collect($tagIdsToAttach)
                ->mapWithKeys(fn (string $tagId): array => [
                    $tagId => ['id' => (string) Str::uuid7()],
                ])
                ->all();

            if ($syncPayload !== []) {
                $this->tagsRelation($model)->syncWithoutDetaching($syncPayload);
            }
        }

        return new EloquentCollection($tags->unique('id')->values()->all());
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     */
    public function detach(Model $model, array $tagsByType): void
    {
        $this->assertModelUsesTags($model);
        $organizationId = $this->resolveOrganizationId($model);
        $normalizedTagsByType = $this->normalizeTagsByType($tagsByType);

        foreach ($normalizedTagsByType as $type => $names) {
            $tags = Tag::query()
                ->where('organization_id', $organizationId)
                ->where('type', $type)
                ->whereIn('name', $names)
                ->get()
                ->keyBy('name');

            $missingNames = $names->reject(fn (string $name): bool => $tags->has($name))->values();
            if ($missingNames->isNotEmpty()) {
                throw TagException::notFound($type, $missingNames->all());
            }

            $tagIds = $tags->pluck('id');
            $linkedTagIds = $this->tagsRelation($model)
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

            $this->tagsRelation($model)->detach($tagIds->all());
        }
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     * @return EloquentCollection<int, Tag>
     */
    public function sync(Model $model, array $tagsByType): EloquentCollection
    {
        $this->assertModelUsesTags($model);
        $this->tagsRelation($model)->detach();

        return $this->attach($model, $tagsByType);
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $tags
     * @return EloquentCollection<int, Tag>
     */
    public function syncForType(Model $model, string $type, Collection|array $tags): EloquentCollection
    {
        $this->assertModelUsesTags($model);
        $normalizedType = str($type)->normalize()->value();

        $existingTagIds = $this->tagsRelation($model)
            ->where('master_tags.type', $normalizedType)
            ->pluck('master_tags.id')
            ->all();

        if ($existingTagIds !== []) {
            $this->tagsRelation($model)->detach($existingTagIds);
        }

        return $this->attach($model, [$normalizedType => $tags]);
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

    protected function resolveOrganizationId(Model $model): string
    {
        if ($model->hasAttribute('organization_id')) {
            $modelOrganizationId = $model->getAttribute('organization_id');
            if (is_string($modelOrganizationId) && $modelOrganizationId !== '') {
                return $modelOrganizationId;
            }
        }

        $organizationId = getPermissionsTeamId();
        if (is_string($organizationId) && $organizationId !== '') {
            return $organizationId;
        }

        throw new InvalidArgumentException(__('master::exceptions.organization_resolution_failed'));
    }

    protected function assertModelUsesTags(Model $model): void
    {
        if (!in_array(HasTags::class, class_uses_recursive($model::class), true)) {
            throw TagException::modelMissingHasTagsTrait($model::class);
        }
    }

    /**
     * @return MorphToMany<Tag, Model>
     */
    protected function tagsRelation(Model $model): MorphToMany
    {
        /** @phpstan-ignore-next-line */
        return $model->tags();
    }
}

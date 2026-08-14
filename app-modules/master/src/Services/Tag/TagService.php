<?php

declare(strict_types=1);

namespace Lahatre\Master\Services\Tag;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Contracts\HasTags as HasTagsContract;
use Lahatre\Master\Exceptions\TagException;
use Lahatre\Master\Models\Tag;
use Lahatre\Master\Traits\InteractsWithTags;
use Lahatre\Shared\Support\HandleGenerator;

class TagService
{
    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $tagsByType
     */
    public function attach(HasTagsContract $model, array $tagsByType): void
    {
        DB::transaction(function () use ($model, $tagsByType): void {
            $this->assertModelUsesTags($model);
            $organizationId = $this->resolveAndValidateOrganizationId($model);
            $normalizedTagsByType = $this->normalizeTagsByType($tagsByType);

            if ($normalizedTagsByType->isEmpty()) {
                return;
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

                    Tag::query()->insertOrIgnore($missingRows);

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
                $alreadyLinkedTagIds = $model->tags()
                    ->whereIn('master_tags.id', $tagIds)
                    ->pluck('master_tags.id')
                    ->all();

                $tagIdsToAttach = array_values(array_diff($tagIds, $alreadyLinkedTagIds));

                $syncPayload = collect($tagIdsToAttach)
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
        $this->assertModelUsesTags($model);
        $this->resolveAndValidateOrganizationId($model);

        DB::transaction(function () use ($model, $tagsByType): void {
            $model->tags()->detach();
            $this->attach($model, $tagsByType);
        });
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $tags
     */
    public function syncForType(HasTagsContract $model, string $type, Collection|array $tags): void
    {
        $this->assertModelUsesTags($model);
        $this->resolveAndValidateOrganizationId($model);
        $normalizedType = str($type)->normalize()->value();

        DB::transaction(function () use ($model, $tags, $normalizedType): void {
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

    protected function resolveAndValidateOrganizationId(HasTagsContract $model): string
    {
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

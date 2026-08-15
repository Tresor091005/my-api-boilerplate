<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Master\Contracts\HasTags as HasTagsContract;
use Lahatre\Master\Data\TagCreateData;
use Lahatre\Master\Data\TagFilterData;
use Lahatre\Master\Data\TagReorderData;
use Lahatre\Master\Data\TagUpdateData;
use Lahatre\Master\Http\Requests\TagCreateRequest;
use Lahatre\Master\Http\Requests\TagFilterRequest;
use Lahatre\Master\Http\Requests\TagReorderRequest;
use Lahatre\Master\Http\Requests\TagUpdateRequest;
use Lahatre\Master\Http\Resources\TagCollection;
use Lahatre\Master\Models\Tag;
use Lahatre\Master\Services\TagService;
use Lahatre\Shared\Registries\MorphMapRegistry;

final class TagController
{
    public function __construct(protected TagService $tagService) {}

    public function index(TagFilterRequest $request): TagCollection
    {
        Gate::authorize('list', Tag::class);

        return $this->tagService->list(TagFilterData::fromArray($request->validated()));
    }

    public function store(TagCreateRequest $request): JsonResponse
    {
        Gate::authorize('create', Tag::class);

        return response()->json(
            $this->tagService->create(TagCreateData::fromArray($request->validated())),
            201,
        );
    }

    public function taggableTags(string $taggableType, string $taggableId): TagCollection
    {
        $taggable = $this->resolveTaggable($taggableType, $taggableId);
        Gate::authorize('retrieve', $taggable);

        return $this->tagService->listForTaggable($taggable);
    }

    public function update(TagUpdateRequest $request, Tag $tag): JsonResponse
    {
        Gate::authorize('update', $tag);

        return response()->json($this->tagService->update($tag, TagUpdateData::fromArray($request->validated())));
    }

    public function reorder(TagReorderRequest $request): JsonResponse
    {
        Gate::authorize('reorder', Tag::class);
        $this->tagService->reorder(TagReorderData::fromArray($request->validated()));

        return response()->json(null, 204);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        Gate::authorize('delete', $tag);
        $this->tagService->delete($tag);

        return response()->json(null, 204);
    }

    private function resolveTaggable(string $taggableType, string $taggableId): HasTagsContract
    {
        $modelClass = app(MorphMapRegistry::class)->getModel($taggableType);

        if ($modelClass === null) {
            throw (new ModelNotFoundException)->setModel(Model::class, [$taggableId]);
        }

        if (!is_a($modelClass, HasTagsContract::class, true)) {
            throw (new ModelNotFoundException)->setModel($modelClass, [$taggableId]);
        }

        /** @var Model&HasTagsContract $taggable */
        $taggable = $modelClass::query()->findOrFail($taggableId);

        return $taggable;
    }
}

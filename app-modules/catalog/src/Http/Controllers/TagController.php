<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\DTO\TagDTO;
use Lahatre\Catalog\DTO\TagFilterDTO;
use Lahatre\Catalog\Http\Resources\TagCollection;
use Lahatre\Catalog\Models\Tag;
use Lahatre\Catalog\Services\TagService;

class TagController
{
    public function __construct(
        protected TagService $tagService
    ) {}

    public function index(Request $request): TagCollection
    {
        Gate::authorize('list', Tag::class);

        $filters = TagFilterDTO::fromRequest($request);

        return $this->tagService->list($filters);
    }

    public function show(Tag $tag): JsonResponse
    {
        Gate::authorize('retrieve', $tag);

        $response = $this->tagService->retrieve($tag);

        return response()->json($response);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Tag::class);

        $dto = TagDTO::fromRequest($request);

        $response = $this->tagService->create($dto);

        return response()->json($response, 201);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        Gate::authorize('update', $tag);

        $dto = TagDTO::forUpdate($request, $tag);

        $response = $this->tagService->update($tag, $dto);

        return response()->json($response);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        Gate::authorize('delete', $tag);

        $this->tagService->delete($tag);

        return response()->json(null, 204);
    }
}

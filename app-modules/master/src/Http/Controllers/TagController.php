<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Master\Data\TagCreateData;
use Lahatre\Master\Data\TagFilterData;
use Lahatre\Master\Data\TagReorderData;
use Lahatre\Master\Data\TagUpdateData;
use Lahatre\Master\Http\Requests\TagCreateRequest;
use Lahatre\Master\Http\Requests\TagFilterRequest;
use Lahatre\Master\Http\Requests\TagReorderRequest;
use Lahatre\Master\Http\Requests\TagUpdateRequest;
use Lahatre\Master\Http\Resources\TagCollection;
use Lahatre\Master\Http\Resources\TagResource;
use Lahatre\Master\Models\Tag;
use Lahatre\Master\Services\TagService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final class TagController
{
    public function __construct(
        protected TagService $tagService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(TagFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Tag::class);

        $filters = TagFilterData::fromArray($request->validated());
        $response = $this->tagService->paginate($filters);

        return $this->responseResponder->respond(fn (): JsonResource => TagCollection::make($response));
    }

    public function store(TagCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Tag::class);

        $response = $this->tagService->create(TagCreateData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => TagCollection::make($response),
            status: 201,
        );
    }

    public function update(TagUpdateRequest $request, Tag $tag): JsonResponse|Response
    {
        Gate::authorize('update', $tag);

        $response = $this->tagService->update($tag, TagUpdateData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => TagResource::make($response));
    }

    public function reorder(TagReorderRequest $request): Response
    {
        Gate::authorize('reorder', Tag::class);
        $this->tagService->reorder(TagReorderData::fromArray($request->validated()));

        return response()->noContent();
    }

    public function destroy(Tag $tag): Response
    {
        Gate::authorize('delete', $tag);
        $this->tagService->delete($tag);

        return response()->noContent();
    }
}

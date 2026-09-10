<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Library\Data\FolderCreateData;
use Lahatre\Library\Data\FolderFilterData;
use Lahatre\Library\Data\FolderUpdateData;
use Lahatre\Library\Http\Requests\FolderCreateRequest;
use Lahatre\Library\Http\Requests\FolderFilterRequest;
use Lahatre\Library\Http\Requests\FolderUpdateRequest;
use Lahatre\Library\Models\Folder;
use Lahatre\Library\Services\FolderService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class FolderController
{
    public function __construct(
        private FolderService $folderService,
        private ResponseResponder $responseResponder,
    ) {}

    public function index(FolderFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Folder::class);
        $resource = $this->folderService->paginate(FolderFilterData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => $resource);
    }

    public function store(FolderCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Folder::class);
        $resource = $this->folderService->create(FolderCreateData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => $resource, status: 201);
    }

    public function show(Folder $folder): JsonResponse|Response
    {
        Gate::authorize('retrieve', $folder);
        $resource = $this->folderService->retrieve($folder);

        return $this->responseResponder->respond(fn (): JsonResource => $resource);
    }

    public function update(FolderUpdateRequest $request, Folder $folder): JsonResponse|Response
    {
        Gate::authorize('update', $folder);
        $resource = $this->folderService->update(
            $folder,
            FolderUpdateData::fromArray($request->validated(), ['name', 'parent_id']),
        );

        return $this->responseResponder->respond(fn (): JsonResource => $resource);
    }

    public function destroy(Folder $folder): Response
    {
        Gate::authorize('delete', $folder);
        $this->folderService->delete($folder);

        return response()->noContent();
    }
}

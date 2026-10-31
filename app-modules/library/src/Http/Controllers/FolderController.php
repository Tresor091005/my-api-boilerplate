<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Library\Data\FolderCreateData;
use Lahatre\Library\Data\FolderFilterData;
use Lahatre\Library\Data\FolderUpdateData;
use Lahatre\Library\Http\Requests\FolderCreateRequest;
use Lahatre\Library\Http\Requests\FolderFilterRequest;
use Lahatre\Library\Http\Requests\FolderUpdateRequest;
use Lahatre\Library\Http\Resources\FolderResource;
use Lahatre\Library\Models\Folder;
use Lahatre\Library\Services\FolderService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class FolderController
{
    public function __construct(
        private FolderService $folderService,
    ) {}

    public function index(FolderFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Folder::class);
        $response = $this->folderService->paginate(FolderFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($response, FolderResource::class);
    }

    public function store(FolderCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Folder::class);
        $response = $this->folderService->create(FolderCreateData::fromArray($request->validated()));

        return ApiResponse::item($response, FolderResource::class, 201);
    }

    public function show(Folder $folder): JsonResponse|Response
    {
        Gate::authorize('retrieve', $folder);
        $response = $this->folderService->retrieve($folder);

        return ApiResponse::item($response, FolderResource::class);
    }

    public function update(FolderUpdateRequest $request, Folder $folder): JsonResponse|Response
    {
        Gate::authorize('update', $folder);
        $response = $this->folderService->update(
            $folder,
            FolderUpdateData::fromArray($request->validated(), ['name', 'parent_id']),
        );

        return ApiResponse::item($response, FolderResource::class);
    }

    public function destroy(Folder $folder): \Illuminate\Http\Response
    {
        Gate::authorize('delete', $folder);
        $this->folderService->delete($folder);

        return ApiResponse::noContent();
    }
}

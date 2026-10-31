<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Library\Data\FileFilterData;
use Lahatre\Library\Data\FileUpdateData;
use Lahatre\Library\Data\FileUploadData;
use Lahatre\Library\Http\Requests\FileFilterRequest;
use Lahatre\Library\Http\Requests\FileUpdateRequest;
use Lahatre\Library\Http\Requests\FileUploadRequest;
use Lahatre\Library\Http\Resources\FileResource;
use Lahatre\Library\Http\Resources\TrashFileResource;
use Lahatre\Library\Http\Responses\PrivateFileResponseFactory;
use Lahatre\Library\Models\File;
use Lahatre\Library\Services\FileService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class FileController
{
    public function __construct(
        private FileService $fileService,
        private PrivateFileResponseFactory $fileResponseFactory,
    ) {}

    public function index(FileFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', File::class);
        $response = $this->fileService->paginate(FileFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($response, FileResource::class);
    }

    public function trash(FileFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', File::class);
        $response = $this->fileService->paginateTrashed(FileFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($response, TrashFileResource::class);
    }

    public function store(FileUploadRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', File::class);
        $response = $this->fileService->upload(FileUploadData::fromArray($request->validated()));

        return ApiResponse::list($response, FileResource::class, 201);
    }

    public function show(File $file): JsonResponse|Response
    {
        Gate::authorize('retrieve', $file);
        $response = $this->fileService->retrieve($file);

        return ApiResponse::item($response, FileResource::class);
    }

    public function update(FileUpdateRequest $request, File $file): JsonResponse|Response
    {
        Gate::authorize('update', $file);
        $response = $this->fileService->update(
            $file,
            FileUpdateData::fromArray($request->validated(), ['name', 'folder_id']),
        );

        return ApiResponse::item($response, FileResource::class);
    }

    public function destroy(File $file): \Illuminate\Http\Response
    {
        Gate::authorize('delete', $file);
        $this->fileService->delete($file);

        return ApiResponse::noContent();
    }

    public function restore(File $file): JsonResponse|Response
    {
        Gate::authorize('restore', $file);
        $response = $this->fileService->restore($file);

        return ApiResponse::item($response, FileResource::class);
    }

    public function content(Request $request, File $file): Response
    {
        Gate::authorize('retrieve', $file);

        return $this->fileResponseFactory->make($request, $file);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Library\Data\FileFilterData;
use Lahatre\Library\Data\FileUpdateData;
use Lahatre\Library\Data\FileUploadData;
use Lahatre\Library\Http\Requests\FileFilterRequest;
use Lahatre\Library\Http\Requests\FileUpdateRequest;
use Lahatre\Library\Http\Requests\FileUploadRequest;
use Lahatre\Library\Http\Resources\FileCollection;
use Lahatre\Library\Http\Resources\FileResource;
use Lahatre\Library\Http\Resources\TrashFileCollection;
use Lahatre\Library\Http\Responses\PrivateFileResponseFactory;
use Lahatre\Library\Models\File;
use Lahatre\Library\Services\FileService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class FileController
{
    public function __construct(
        private FileService $fileService,
        private PrivateFileResponseFactory $fileResponseFactory,
        private ResponseResponder $responseResponder,
    ) {}

    public function index(FileFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', File::class);
        $response = $this->fileService->paginate(FileFilterData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => FileCollection::make($response)
        );
    }

    public function trash(FileFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', File::class);
        $response = $this->fileService->paginateTrashed(FileFilterData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => TrashFileCollection::make($response)
        );
    }

    public function store(FileUploadRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', File::class);
        $response = $this->fileService->upload(FileUploadData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => FileCollection::make($response),
            status: 201,
        );
    }

    public function show(File $file): JsonResponse|Response
    {
        Gate::authorize('retrieve', $file);
        $response = $this->fileService->retrieve($file);

        return $this->responseResponder->respond(
            fn (): JsonResource => FileResource::make($response)
        );
    }

    public function update(FileUpdateRequest $request, File $file): JsonResponse|Response
    {
        Gate::authorize('update', $file);
        $response = $this->fileService->update(
            $file,
            FileUpdateData::fromArray($request->validated(), ['name', 'folder_id']),
        );

        return $this->responseResponder->respond(
            fn (): JsonResource => FileResource::make($response)
        );
    }

    public function destroy(File $file): Response
    {
        Gate::authorize('delete', $file);
        $this->fileService->delete($file);

        return response()->noContent();
    }

    public function restore(File $file): JsonResponse|Response
    {
        Gate::authorize('restore', $file);
        $response = $this->fileService->restore($file);

        return $this->responseResponder->respond(
            fn (): JsonResource => FileResource::make($response)
        );
    }

    public function content(Request $request, File $file): Response
    {
        Gate::authorize('retrieve', $file);

        return $this->fileResponseFactory->make($request, $file);
    }
}

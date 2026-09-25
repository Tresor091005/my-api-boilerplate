<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Http\Resources\ServiceResource;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Services\CatalogFileService;
use Lahatre\Library\Http\Requests\AttachmentListRequest;
use Lahatre\Library\Http\Requests\FileListRequest;
use Lahatre\Library\Http\Requests\SingleFileRequest;
use Lahatre\Library\Http\Responses\PrivateFileResponseFactory;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServiceFileController
{
    public function __construct(
        private CatalogFileService $files,
        private PrivateFileResponseFactory $fileResponses,
        private ResponseResponder $responses,
    ) {}

    public function updateMain(SingleFileRequest $request, Service $service): JsonResponse|Response
    {
        Gate::authorize('update', $service);
        $fileId = $request->validated('file_id');
        $service = $this->files->setMain($service, $fileId === null ? [] : [$fileId]);

        return $this->responses->respond(fn (): JsonResource => ServiceResource::make($service));
    }

    public function storeGallery(FileListRequest $request, Service $service): JsonResponse|Response
    {
        Gate::authorize('update', $service);
        $service = $this->files->addGallery($service, $request->validated('file_ids'));

        return $this->responses->respond(fn (): JsonResource => ServiceResource::make($service), status: 201);
    }

    public function updateGallery(AttachmentListRequest $request, Service $service): JsonResponse|Response
    {
        Gate::authorize('update', $service);
        $service = $this->files->reorderGallery($service, $request->validated('attachment_ids'));

        return $this->responses->respond(fn (): JsonResource => ServiceResource::make($service));
    }

    public function destroyGallery(AttachmentListRequest $request, Service $service): Response
    {
        Gate::authorize('update', $service);
        $this->files->removeGallery($service, $request->validated('attachment_ids'));

        return response()->noContent();
    }

    public function content(Request $request, Service $service, string $attachment): Response
    {
        Gate::authorize('retrieve', $service);

        return $this->fileResponses->make($request, $this->files->find($service, $attachment)->linkedFile());
    }
}

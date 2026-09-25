<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Http\Resources\ProductResource;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Services\CatalogFileService;
use Lahatre\Library\Http\Requests\AttachmentListRequest;
use Lahatre\Library\Http\Requests\FileListRequest;
use Lahatre\Library\Http\Requests\SingleFileRequest;
use Lahatre\Library\Http\Responses\PrivateFileResponseFactory;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class ProductFileController
{
    public function __construct(
        private CatalogFileService $files,
        private PrivateFileResponseFactory $fileResponses,
        private ResponseResponder $responses,
    ) {}

    public function updateMain(SingleFileRequest $request, Product $product): JsonResponse|Response
    {
        Gate::authorize('update', $product);
        $fileId = $request->validated('file_id');
        $product = $this->files->setMain($product, $fileId === null ? [] : [$fileId]);

        return $this->responses->respond(fn (): JsonResource => ProductResource::make($product));
    }

    public function storeGallery(FileListRequest $request, Product $product): JsonResponse|Response
    {
        Gate::authorize('update', $product);
        $product = $this->files->addGallery($product, $request->validated('file_ids'));

        return $this->responses->respond(fn (): JsonResource => ProductResource::make($product), status: 201);
    }

    public function updateGallery(AttachmentListRequest $request, Product $product): JsonResponse|Response
    {
        Gate::authorize('update', $product);
        $product = $this->files->reorderGallery($product, $request->validated('attachment_ids'));

        return $this->responses->respond(fn (): JsonResource => ProductResource::make($product));
    }

    public function destroyGallery(AttachmentListRequest $request, Product $product): Response
    {
        Gate::authorize('update', $product);
        $this->files->removeGallery($product, $request->validated('attachment_ids'));

        return response()->noContent();
    }

    public function content(Request $request, Product $product, string $attachment): Response
    {
        Gate::authorize('retrieve', $product);

        return $this->fileResponses->make($request, $this->files->find($product, $attachment)->linkedFile());
    }
}

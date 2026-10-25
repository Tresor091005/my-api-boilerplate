<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\BundleStockOperationData;
use Lahatre\Catalog\Data\BundleStockOperationFilterData;
use Lahatre\Catalog\Http\Requests\BundleStockOperationCreateRequest;
use Lahatre\Catalog\Http\Requests\BundleStockOperationFilterRequest;
use Lahatre\Catalog\Http\Resources\BundleStockOperationResource;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleStockOperation;
use Lahatre\Catalog\Services\BundleStockOperationService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class BundleStockOperationController
{
    public function __construct(
        private BundleStockOperationService $operationService,
    ) {}

    public function index(
        BundleStockOperationFilterRequest $request,
        Bundle $bundle,
    ): JsonResponse|Response {
        Gate::authorize('assemble', $bundle);
        $operations = $this->operationService->paginate(
            $bundle,
            BundleStockOperationFilterData::fromArray($request->validated()),
        );

        return ApiResponse::cursorPage($operations, BundleStockOperationResource::class);
    }

    public function store(
        BundleStockOperationCreateRequest $request,
        Bundle $bundle,
    ): JsonResponse|Response {
        Gate::authorize('assemble', $bundle);
        $operation = $this->operationService->create(
            $bundle,
            BundleStockOperationData::fromArray($request->validated()),
        );

        return ApiResponse::item($operation, BundleStockOperationResource::class, 201);
    }

    public function show(Bundle $bundle, BundleStockOperation $stockOperation): JsonResponse|Response
    {
        Gate::authorize('assemble', $bundle);
        $operation = $this->operationService->retrieve($bundle, $stockOperation->id);

        return ApiResponse::item($operation, BundleStockOperationResource::class);
    }

    public function complete(Bundle $bundle, BundleStockOperation $stockOperation): JsonResponse|Response
    {
        Gate::authorize('assemble', $bundle);
        $operation = $this->operationService->complete($bundle, $stockOperation->id);

        return ApiResponse::item($operation, BundleStockOperationResource::class);
    }
}

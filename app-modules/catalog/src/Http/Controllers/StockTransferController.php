<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\StockTransferData;
use Lahatre\Catalog\Data\StockTransferFilterData;
use Lahatre\Catalog\Http\Requests\StockTransferFilterRequest;
use Lahatre\Catalog\Http\Requests\StockTransferRequest;
use Lahatre\Catalog\Http\Resources\StockTransferResource;
use Lahatre\Catalog\Models\StockTransfer;
use Lahatre\Catalog\Services\StockTransferService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class StockTransferController
{
    public function __construct(
        private StockTransferService $stockTransferService,
    ) {}

    public function index(StockTransferFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', StockTransfer::class);
        $transfers = $this->stockTransferService->paginate(StockTransferFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($transfers, StockTransferResource::class);
    }

    public function store(StockTransferRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', StockTransfer::class);
        $transfer = $this->stockTransferService->create(StockTransferData::fromArray($request->validated()));

        return ApiResponse::item($transfer, StockTransferResource::class, 201);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse|Response
    {
        Gate::authorize('retrieve', $stockTransfer);

        return ApiResponse::item($this->stockTransferService->retrieve($stockTransfer), StockTransferResource::class);
    }

    public function update(StockTransferRequest $request, StockTransfer $stockTransfer): JsonResponse|Response
    {
        Gate::authorize('update', $stockTransfer);
        $transfer = $this->stockTransferService->update($stockTransfer, StockTransferData::fromArray($request->validated()));

        return ApiResponse::item($transfer, StockTransferResource::class);
    }

    public function destroy(StockTransfer $stockTransfer): Response
    {
        Gate::authorize('delete', $stockTransfer);
        $this->stockTransferService->delete($stockTransfer);

        return ApiResponse::noContent();
    }

    public function complete(StockTransfer $stockTransfer): JsonResponse|Response
    {
        Gate::authorize('complete', $stockTransfer);
        $transfer = $this->stockTransferService->complete($stockTransfer);

        return ApiResponse::item($transfer, StockTransferResource::class);
    }

    public function cancel(StockTransfer $stockTransfer): JsonResponse|Response
    {
        Gate::authorize('cancel', $stockTransfer);
        $transfer = $this->stockTransferService->cancel($stockTransfer);

        return ApiResponse::item($transfer, StockTransferResource::class);
    }
}

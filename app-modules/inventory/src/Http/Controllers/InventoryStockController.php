<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Inventory\Data\InventoryStockFilterData;
use Lahatre\Inventory\Data\InventoryStockSummaryFilterData;
use Lahatre\Inventory\Http\Requests\InventoryStockFilterRequest;
use Lahatre\Inventory\Http\Requests\InventoryStockSummaryFilterRequest;
use Lahatre\Inventory\Http\Requests\UpdateInventoryStockMetadataRequest;
use Lahatre\Inventory\Http\Resources\InventoryStockResource;
use Lahatre\Inventory\Http\Resources\InventorySummaryCollection;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\Inventory\Services\Stock\ManageInventoryStockService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class InventoryStockController
{
    public function __construct(
        protected ManageInventoryStockService $inventoryStockService,
        protected InventoryQueryService $inventoryQueryService,
    ) {}

    public function update(UpdateInventoryStockMetadataRequest $request, InventoryStock $stock): JsonResponse|Response
    {
        Gate::authorize('update', $stock);

        /** @var array<string, mixed>|null $metadata */
        $metadata = $request->validated('metadata');

        $stock = $this->inventoryStockService->updateMetadata($stock, $metadata);

        return ApiResponse::item($stock, InventoryStockResource::class);
    }

    public function index(InventoryStockFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', InventoryStock::class);

        $filters = InventoryStockFilterData::fromArray($request->validated());

        $stocks = $this->inventoryQueryService->paginateStocks($filters);

        return ApiResponse::cursorPage($stocks, InventoryStockResource::class);
    }

    public function indexSummary(InventoryStockSummaryFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', InventoryStock::class);

        $filters = InventoryStockSummaryFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->paginateSummary($filters);

        return InventorySummaryCollection::make($response)->response();
    }
}

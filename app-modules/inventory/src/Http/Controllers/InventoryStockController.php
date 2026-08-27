<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Inventory\Data\InventoryLotFilterData;
use Lahatre\Inventory\Data\InventoryStockFilterData;
use Lahatre\Inventory\Data\InventoryStockSummaryFilterData;
use Lahatre\Inventory\Http\Requests\InventoryLotFilterRequest;
use Lahatre\Inventory\Http\Requests\InventoryStockFilterRequest;
use Lahatre\Inventory\Http\Requests\InventoryStockSummaryFilterRequest;
use Lahatre\Inventory\Http\Requests\UpdateInventoryStockMetadataRequest;
use Lahatre\Inventory\Http\Resources\InventoryItemLocationLotsResource;
use Lahatre\Inventory\Http\Resources\InventoryStockCollection;
use Lahatre\Inventory\Http\Resources\InventoryStockResource;
use Lahatre\Inventory\Http\Resources\InventorySummaryCollection;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\Inventory\Services\Stock\ManageInventoryStockService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class InventoryStockController
{
    public function __construct(
        protected ManageInventoryStockService $inventoryStockService,
        protected InventoryQueryService $inventoryQueryService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function update(UpdateInventoryStockMetadataRequest $request, InventoryStock $stock): JsonResponse|Response
    {
        Gate::authorize('update', $stock);

        /** @var array<string, mixed>|null $metadata */
        $metadata = $request->validated('metadata');

        $response = $this->inventoryStockService->updateMetadata($stock, $metadata);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryStockResource::make($response));
    }

    public function index(InventoryStockFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', InventoryStock::class);

        $filters = InventoryStockFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->paginateStocks($filters);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryStockCollection::make($response));
    }

    public function indexSummary(InventoryStockSummaryFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', InventoryStock::class);

        $filters = InventoryStockSummaryFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->paginateSummary($filters);

        return $this->responseResponder->respond(fn (): JsonResource => InventorySummaryCollection::make($response));
    }

    public function indexLocationLots(
        InventoryLotFilterRequest $request,
        InventoryItem $item,
        InventoryLocation $location,
    ): JsonResponse|Response {
        Gate::authorize('list', InventoryStock::class);

        $filters = InventoryLotFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->getItemLocationLots($item, $location, $filters);

        return $this->responseResponder->respond(
            fn (): JsonResource => InventoryItemLocationLotsResource::make($response)
        );
    }
}

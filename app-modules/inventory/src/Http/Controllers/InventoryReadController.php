<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Data\InventoryStockExpiringFilterData;
use Lahatre\Inventory\Data\InventoryStockSummaryFilterData;
use Lahatre\Inventory\Http\Requests\InventoryStockExpiringFilterRequest;
use Lahatre\Inventory\Http\Requests\InventoryStockSummaryFilterRequest;
use Lahatre\Inventory\Http\Resources\InventoryExpiringLotCollection;
use Lahatre\Inventory\Http\Resources\InventorySummaryCollection;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class InventoryReadController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function indexSummary(InventoryStockSummaryFilterRequest $request): JsonResponse|Response
    {
        $filters = InventoryStockSummaryFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->paginateSummary($filters);

        return $this->responseResponder->respond(fn (): JsonResource => InventorySummaryCollection::make($response));
    }

    public function indexExpiring(InventoryStockExpiringFilterRequest $request): JsonResponse|Response
    {
        $filters = InventoryStockExpiringFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->paginateExpiring($filters);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryExpiringLotCollection::make($response));
    }
}

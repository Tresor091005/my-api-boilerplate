<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Lahatre\Inventory\DTO\InventoryLotFilterDTO;
use Lahatre\Inventory\DTO\InventoryMovementFilterDTO;
use Lahatre\Inventory\DTO\InventoryStockExpiringFilterDTO;
use Lahatre\Inventory\DTO\InventoryStockSummaryFilterDTO;
use Lahatre\Inventory\Http\Resources\InventoryExpiringLotResource;
use Lahatre\Inventory\Http\Resources\InventoryItemLocationLotsViewResource;
use Lahatre\Inventory\Http\Resources\InventoryItemStockViewResource;
use Lahatre\Inventory\Http\Resources\InventoryLocationStockViewResource;
use Lahatre\Inventory\Http\Resources\InventoryMovementResource;
use Lahatre\Inventory\Http\Resources\InventorySummaryResource;
use Lahatre\Inventory\Http\Resources\InventoryTransactionResource;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Services\InventoryQueryService;

class InventoryReadController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService
    ) {}

    public function showItemStock(InventoryItem $item): JsonResponse
    {
        return response()->json(
            InventoryItemStockViewResource::make($this->inventoryQueryService->getItemStock($item))->resolve()
        );
    }

    public function showLocationStock(InventoryLocation $location): JsonResponse
    {
        return response()->json(
            InventoryLocationStockViewResource::make($this->inventoryQueryService->getLocationStock($location))->resolve()
        );
    }

    public function indexItemLocationLots(Request $request, InventoryItem $item, InventoryLocation $location): JsonResponse
    {
        $filters = InventoryLotFilterDTO::fromRequest($request);

        return response()->json(
            InventoryItemLocationLotsViewResource::make(
                $this->inventoryQueryService->getItemLocationLots($item, $location, $filters)
            )->resolve()
        );
    }

    public function indexSummary(Request $request): JsonResponse
    {
        $filters = InventoryStockSummaryFilterDTO::fromRequest($request);
        $paginator = $this->inventoryQueryService->getSummary($filters);

        return $this->paginatedResponse(
            $paginator,
            InventorySummaryResource::collection(collect($paginator->items()))->resolve()
        );
    }

    public function indexExpiring(Request $request): JsonResponse
    {
        $filters = InventoryStockExpiringFilterDTO::fromRequest($request);
        $paginator = $this->inventoryQueryService->getExpiring($filters);

        return $this->paginatedResponse(
            $paginator,
            InventoryExpiringLotResource::collection(collect($paginator->items()))->resolve()
        );
    }

    public function indexItemMovements(Request $request, InventoryItem $item): JsonResponse
    {
        $filters = InventoryMovementFilterDTO::fromRequest($request);
        $paginator = $this->inventoryQueryService->getItemMovements($item, $filters);

        return $this->paginatedResponse(
            $paginator,
            InventoryMovementResource::collection(collect($paginator->items()))->resolve()
        );
    }

    public function indexLocationMovements(Request $request, InventoryLocation $location): JsonResponse
    {
        $filters = InventoryMovementFilterDTO::fromRequest($request);
        $paginator = $this->inventoryQueryService->getLocationMovements($location, $filters);

        return $this->paginatedResponse(
            $paginator,
            InventoryMovementResource::collection(collect($paginator->items()))->resolve()
        );
    }

    public function showTransaction(InventoryTransaction $transaction): JsonResponse
    {
        $transaction = $this->inventoryQueryService->getTransaction($transaction);

        return response()->json(InventoryTransactionResource::make($transaction)->resolve());
    }

    private function paginatedResponse(LengthAwarePaginator $paginator, array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Lahatre\Inventory\Data\InventoryStockExpiringFilterData;
use Lahatre\Inventory\Data\InventoryStockSummaryFilterData;
use Lahatre\Inventory\Http\Requests\InventoryStockExpiringFilterRequest;
use Lahatre\Inventory\Http\Requests\InventoryStockSummaryFilterRequest;
use Lahatre\Inventory\Http\Resources\InventoryExpiringLotCollection;
use Lahatre\Inventory\Http\Resources\InventorySummaryCollection;
use Lahatre\Inventory\Services\InventoryQueryService;

class InventoryReadController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService
    ) {}

    public function indexSummary(InventoryStockSummaryFilterRequest $request): InventorySummaryCollection
    {
        $filters = InventoryStockSummaryFilterData::fromArray($request->validated());

        return $this->inventoryQueryService->listSummary($filters);
    }

    public function indexExpiring(InventoryStockExpiringFilterRequest $request): InventoryExpiringLotCollection
    {
        $filters = InventoryStockExpiringFilterData::fromArray($request->validated());

        return $this->inventoryQueryService->listExpiring($filters);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\Request;
use Lahatre\Inventory\DTO\InventoryStockExpiringFilterDTO;
use Lahatre\Inventory\DTO\InventoryStockSummaryFilterDTO;
use Lahatre\Inventory\Http\Resources\InventoryExpiringLotCollection;
use Lahatre\Inventory\Http\Resources\InventorySummaryCollection;
use Lahatre\Inventory\Services\InventoryQueryService;

class InventoryReadController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService
    ) {}

    public function indexSummary(Request $request): InventorySummaryCollection
    {
        $filters = InventoryStockSummaryFilterDTO::fromRequest($request);

        return $this->inventoryQueryService->listSummary($filters);
    }

    public function indexExpiring(Request $request): InventoryExpiringLotCollection
    {
        $filters = InventoryStockExpiringFilterDTO::fromRequest($request);

        return $this->inventoryQueryService->listExpiring($filters);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Inventory\DTO\InventoryStockMetadataDTO;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Services\Stock\ManageInventoryStockService;

class InventoryStockController
{
    public function __construct(
        protected ManageInventoryStockService $inventoryStockService,
    ) {}

    public function update(Request $request, InventoryStock $stock): JsonResponse
    {
        Gate::authorize('update', $stock);

        $dto = InventoryStockMetadataDTO::fromRequest($request);

        return response()->json(
            $this->inventoryStockService->updateMetadata($stock, $dto->metadata)
        );
    }
}

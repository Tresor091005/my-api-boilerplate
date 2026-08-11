<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Inventory\Http\Requests\UpdateInventoryStockMetadataRequest;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Services\Stock\ManageInventoryStockService;

class InventoryStockController
{
    public function __construct(
        protected ManageInventoryStockService $inventoryStockService,
    ) {}

    public function update(UpdateInventoryStockMetadataRequest $request, InventoryStock $stock): JsonResponse
    {
        Gate::authorize('update', $stock);

        /** @var array<string, mixed>|null $metadata */
        $metadata = $request->validated('metadata');

        return response()->json(
            $this->inventoryStockService->updateMetadata($stock, $metadata)
        );
    }
}

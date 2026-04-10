<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lahatre\Inventory\DTO\InventoryTransactionFilterDTO;
use Lahatre\Inventory\Http\Resources\InventoryTransactionCollection;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Services\InventoryQueryService;

class InventoryTransactionController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService
    ) {}

    public function index(Request $request): InventoryTransactionCollection
    {
        $filters = InventoryTransactionFilterDTO::fromRequest($request);

        return $this->inventoryQueryService->listTransactions($filters);
    }

    public function show(InventoryTransaction $transaction): JsonResponse
    {
        $resource = $this->inventoryQueryService->retrieveTransaction($transaction);

        return response()->json($resource);
    }
}

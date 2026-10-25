<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Inventory\Data\InventoryTransactionFilterData;
use Lahatre\Inventory\Http\Requests\InventoryTransactionFilterRequest;
use Lahatre\Inventory\Http\Resources\InventoryTransactionResource;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class InventoryTransactionController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService,
    ) {}

    public function index(InventoryTransactionFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', InventoryTransaction::class);

        $filters = InventoryTransactionFilterData::fromArray($request->validated());

        $transactions = $this->inventoryQueryService->paginateTransactions($filters);

        return ApiResponse::cursorPage($transactions, InventoryTransactionResource::class);
    }

    public function show(InventoryTransaction $transaction): JsonResponse|Response
    {
        Gate::authorize('retrieve', $transaction);

        $transaction = $this->inventoryQueryService->retrieveTransaction($transaction);

        return ApiResponse::item($transaction, InventoryTransactionResource::class);
    }
}

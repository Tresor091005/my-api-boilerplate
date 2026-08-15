<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Data\InventoryTransactionFilterData;
use Lahatre\Inventory\Http\Requests\InventoryTransactionFilterRequest;
use Lahatre\Inventory\Http\Resources\InventoryTransactionCollection;
use Lahatre\Inventory\Http\Resources\InventoryTransactionResource;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class InventoryTransactionController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(InventoryTransactionFilterRequest $request): JsonResponse|Response
    {
        $filters = InventoryTransactionFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->paginateTransactions($filters);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryTransactionCollection::make($response));
    }

    public function show(InventoryTransaction $transaction): JsonResponse|Response
    {
        $transaction = $this->inventoryQueryService->retrieveTransaction($transaction);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryTransactionResource::make($transaction));
    }
}

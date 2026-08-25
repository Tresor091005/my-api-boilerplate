<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Data\InventoryMovementFilterData;
use Lahatre\Inventory\Http\Requests\InventoryMovementFilterRequest;
use Lahatre\Inventory\Http\Resources\InventoryMovementCollection;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class InventoryMovementController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(InventoryMovementFilterRequest $request): JsonResponse|Response
    {
        $filters = InventoryMovementFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->paginateMovements($filters);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryMovementCollection::make($response));
    }
}

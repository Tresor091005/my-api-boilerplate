<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Inventory\Data\InventoryMovementFilterData;
use Lahatre\Inventory\Http\Requests\InventoryMovementFilterRequest;
use Lahatre\Inventory\Http\Resources\InventoryMovementResource;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class InventoryMovementController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService,
    ) {}

    public function index(InventoryMovementFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', InventoryMovement::class);

        $filters = InventoryMovementFilterData::fromArray($request->validated());

        $movements = $this->inventoryQueryService->paginateMovements($filters);

        return ApiResponse::cursorPage($movements, InventoryMovementResource::class);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Lahatre\Inventory\Data\InventoryLocationFilterData;
use Lahatre\Inventory\Data\InventoryLocationValueFilterData;
use Lahatre\Inventory\Data\InventoryMovementFilterData;
use Lahatre\Inventory\Http\Requests\InventoryLocationFilterRequest;
use Lahatre\Inventory\Http\Requests\InventoryLocationValueFilterRequest;
use Lahatre\Inventory\Http\Requests\InventoryMovementFilterRequest;
use Lahatre\Inventory\Http\Resources\InventoryLocationCollection;
use Lahatre\Inventory\Http\Resources\InventoryLocationResource;
use Lahatre\Inventory\Http\Resources\InventoryMovementCollection;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class InventoryLocationController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(InventoryLocationFilterRequest $request): JsonResponse|Response
    {
        $filters = InventoryLocationFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->paginateLocations($filters);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryLocationCollection::make($response));
    }

    public function show(Request $request, InventoryLocation $location): JsonResponse|Response
    {
        $location = $this->inventoryQueryService->retrieveLocation($location);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryLocationResource::make($location));
    }

    public function showStock(InventoryLocation $location): JsonResponse|Response
    {
        $response = $this->inventoryQueryService->getLocationStock($location);

        return $this->responseResponder->respond(fn (): JsonSerializable => $response);
    }

    public function showValue(InventoryLocationValueFilterRequest $request, InventoryLocation $location): JsonResponse|Response
    {
        $filters = InventoryLocationValueFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->getLocationValue($location, $filters);

        return $this->responseResponder->respond(fn (): JsonSerializable => $response);
    }

    public function indexMovements(InventoryMovementFilterRequest $request, InventoryLocation $location): JsonResponse|Response
    {
        $filters = InventoryMovementFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->paginateLocationMovements($location, $filters);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryMovementCollection::make($response));
    }
}

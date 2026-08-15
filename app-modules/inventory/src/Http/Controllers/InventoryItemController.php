<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use JsonSerializable;
use Lahatre\Inventory\Data\InventoryItemFilterData;
use Lahatre\Inventory\Data\InventoryItemValueFilterData;
use Lahatre\Inventory\Data\InventoryLotFilterData;
use Lahatre\Inventory\Data\InventoryMovementFilterData;
use Lahatre\Inventory\Http\Requests\InventoryItemFilterRequest;
use Lahatre\Inventory\Http\Requests\InventoryItemValueFilterRequest;
use Lahatre\Inventory\Http\Requests\InventoryLotFilterRequest;
use Lahatre\Inventory\Http\Requests\InventoryMovementFilterRequest;
use Lahatre\Inventory\Http\Requests\UpdateInventoryItemRequest;
use Lahatre\Inventory\Http\Resources\InventoryItemCollection;
use Lahatre\Inventory\Http\Resources\InventoryItemResource;
use Lahatre\Inventory\Http\Resources\InventoryMovementCollection;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\Inventory\Services\Item\ManageInventoryItemService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class InventoryItemController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService,
        protected ManageInventoryItemService $inventoryItemService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(InventoryItemFilterRequest $request): JsonResponse|Response
    {
        $filters = InventoryItemFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->paginateItems($filters);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryItemCollection::make($response));
    }

    public function show(Request $request, InventoryItem $item): JsonResponse|Response
    {
        $item = $this->inventoryQueryService->retrieveItem($item);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryItemResource::make($item));
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $item): JsonResponse|Response
    {
        Gate::authorize('update', $item);

        $response = $this->inventoryItemService->updateRecord($item, $request->validated());

        return $this->responseResponder->respond(
            fn (): JsonResource => InventoryItemResource::make($response),
        );
    }

    public function showStock(InventoryItem $item): JsonResponse|Response
    {
        $response = $this->inventoryQueryService->getItemStock($item);

        return $this->responseResponder->respond(fn (): JsonSerializable => $response);
    }

    public function showValue(InventoryItemValueFilterRequest $request, InventoryItem $item): JsonResponse|Response
    {
        $filters = InventoryItemValueFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->getItemValue($item, $filters);

        return $this->responseResponder->respond(fn (): JsonSerializable => $response);
    }

    public function indexMovements(InventoryMovementFilterRequest $request, InventoryItem $item): JsonResponse|Response
    {
        $filters = InventoryMovementFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->paginateItemMovements($item, $filters);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryMovementCollection::make($response));
    }

    public function indexLocationLots(InventoryLotFilterRequest $request, InventoryItem $item, InventoryLocation $location): JsonResponse|Response
    {
        $filters = InventoryLotFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->getItemLocationLots($item, $location, $filters);

        return $this->responseResponder->respond(fn (): JsonSerializable => $response);
    }
}

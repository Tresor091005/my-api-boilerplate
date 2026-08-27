<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JsonSerializable;
use Lahatre\Inventory\Data\InventoryLotFilterData;
use Lahatre\Inventory\Http\Requests\InventoryLotFilterRequest;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class InventoryItemController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function indexLocationLots(InventoryLotFilterRequest $request, InventoryItem $item, InventoryLocation $location): JsonResponse|Response
    {
        $filters = InventoryLotFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->getItemLocationLots($item, $location, $filters);

        return $this->responseResponder->respond(fn (): JsonSerializable => $response);
    }
}

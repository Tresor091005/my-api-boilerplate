<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JsonSerializable;
use Lahatre\Inventory\Data\InventoryLocationValueFilterData;
use Lahatre\Inventory\Http\Requests\InventoryLocationValueFilterRequest;
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

    public function showValue(InventoryLocationValueFilterRequest $request, InventoryLocation $location): JsonResponse|Response
    {
        $filters = InventoryLocationValueFilterData::fromArray($request->validated());

        $response = $this->inventoryQueryService->getLocationValue($location, $filters);

        return $this->responseResponder->respond(fn (): JsonSerializable => $response);
    }
}

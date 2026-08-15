<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Inventory\Http\Requests\UpdateInventoryStockMetadataRequest;
use Lahatre\Inventory\Http\Resources\InventoryStockResource;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Services\Stock\ManageInventoryStockService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class InventoryStockController
{
    public function __construct(
        protected ManageInventoryStockService $inventoryStockService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function update(UpdateInventoryStockMetadataRequest $request, InventoryStock $stock): JsonResponse|Response
    {
        Gate::authorize('update', $stock);

        /** @var array<string, mixed>|null $metadata */
        $metadata = $request->validated('metadata');

        $response = $this->inventoryStockService->updateMetadata($stock, $metadata);

        return $this->responseResponder->respond(fn (): JsonResource => InventoryStockResource::make($response));
    }
}

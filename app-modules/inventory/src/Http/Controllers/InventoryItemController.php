<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
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
use Lahatre\Inventory\Http\Resources\InventoryMovementCollection;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Services\InventoryQueryService;
use Lahatre\Inventory\Services\Item\ManageInventoryItemService;

class InventoryItemController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService,
        protected ManageInventoryItemService $inventoryItemService,
    ) {}

    public function index(InventoryItemFilterRequest $request): InventoryItemCollection
    {
        $includes = $this->includes($request);
        $filters = InventoryItemFilterData::fromArray($request->validated());

        return $this->inventoryQueryService->listItems(
            $filters,
            includeItemable: $includes->contains('itemable')
        );
    }

    public function show(Request $request, InventoryItem $item): JsonResponse
    {
        $includes = $this->includes($request);

        $resource = $this->inventoryQueryService->retrieveItem(
            $item,
            includeItemable: $includes->contains('itemable')
        );

        return response()->json($resource);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $item): JsonResponse
    {
        Gate::authorize('update', $item);

        return response()->json(
            $this->inventoryItemService->updateRecord($item, $request->validated())
        );
    }

    public function showStock(InventoryItem $item): JsonResponse
    {
        return response()->json($this->inventoryQueryService->getItemStock($item));
    }

    public function showValue(InventoryItemValueFilterRequest $request, InventoryItem $item): JsonResponse
    {
        $filters = InventoryItemValueFilterData::fromArray($request->validated());

        return response()->json($this->inventoryQueryService->getItemValue($item, $filters));
    }

    public function indexMovements(InventoryMovementFilterRequest $request, InventoryItem $item): InventoryMovementCollection
    {
        $filters = InventoryMovementFilterData::fromArray($request->validated());

        return $this->inventoryQueryService->listItemMovements($item, $filters);
    }

    public function indexLocationLots(InventoryLotFilterRequest $request, InventoryItem $item, InventoryLocation $location): JsonResponse
    {
        $filters = InventoryLotFilterData::fromArray($request->validated());

        return response()->json(
            $this->inventoryQueryService->getItemLocationLots($item, $location, $filters)
        );
    }

    /**
     * @return Collection<int, string>
     */
    private function includes(Request $request): Collection
    {
        /** @var Collection<int, string> $includes */
        $includes = collect(explode(',', (string) $request->query('include', '')))
            ->map(fn (string $include): string => trim($include))
            ->filter()
            ->values();

        return $includes;
    }
}

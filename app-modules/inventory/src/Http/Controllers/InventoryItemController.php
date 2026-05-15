<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Lahatre\Inventory\DTO\InventoryItemFilterDTO;
use Lahatre\Inventory\DTO\InventoryItemValueFilterDTO;
use Lahatre\Inventory\DTO\InventoryLotFilterDTO;
use Lahatre\Inventory\DTO\InventoryMovementFilterDTO;
use Lahatre\Inventory\Http\Resources\InventoryItemCollection;
use Lahatre\Inventory\Http\Resources\InventoryMovementCollection;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Services\InventoryQueryService;

class InventoryItemController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService
    ) {}

    public function index(Request $request): InventoryItemCollection
    {
        $includes = $this->includes($request);
        $filters = InventoryItemFilterDTO::fromRequest($request);

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

    public function showStock(InventoryItem $item): JsonResponse
    {
        return response()->json($this->inventoryQueryService->getItemStock($item));
    }

    public function showValue(Request $request, InventoryItem $item): JsonResponse
    {
        $filters = InventoryItemValueFilterDTO::fromRequest($request);

        return response()->json($this->inventoryQueryService->getItemValue($item, $filters));
    }

    public function indexMovements(Request $request, InventoryItem $item): InventoryMovementCollection
    {
        $filters = InventoryMovementFilterDTO::fromRequest($request);

        return $this->inventoryQueryService->listItemMovements($item, $filters);
    }

    public function indexLocationLots(Request $request, InventoryItem $item, InventoryLocation $location): JsonResponse
    {
        $filters = InventoryLotFilterDTO::fromRequest($request);

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

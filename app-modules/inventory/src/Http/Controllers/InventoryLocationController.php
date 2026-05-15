<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Lahatre\Inventory\DTO\InventoryLocationFilterDTO;
use Lahatre\Inventory\DTO\InventoryLocationValueFilterDTO;
use Lahatre\Inventory\DTO\InventoryMovementFilterDTO;
use Lahatre\Inventory\Http\Resources\InventoryLocationCollection;
use Lahatre\Inventory\Http\Resources\InventoryMovementCollection;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Services\InventoryQueryService;

class InventoryLocationController
{
    public function __construct(
        protected InventoryQueryService $inventoryQueryService
    ) {}

    public function index(Request $request): InventoryLocationCollection
    {
        $includes = $this->includes($request);
        $filters = InventoryLocationFilterDTO::fromRequest($request);

        return $this->inventoryQueryService->listLocations(
            $filters,
            includeExternal: $includes->contains('external')
        );
    }

    public function show(Request $request, InventoryLocation $location): JsonResponse
    {
        $includes = $this->includes($request);

        $resource = $this->inventoryQueryService->retrieveLocation(
            $location,
            includeExternal: $includes->contains('external')
        );

        return response()->json($resource);
    }

    public function showStock(InventoryLocation $location): JsonResponse
    {
        return response()->json($this->inventoryQueryService->getLocationStock($location));
    }

    public function showValue(Request $request, InventoryLocation $location): JsonResponse
    {
        $filters = InventoryLocationValueFilterDTO::fromRequest($request);

        return response()->json($this->inventoryQueryService->getLocationValue($location, $filters));
    }

    public function indexMovements(Request $request, InventoryLocation $location): InventoryMovementCollection
    {
        $filters = InventoryMovementFilterDTO::fromRequest($request);

        return $this->inventoryQueryService->listLocationMovements($location, $filters);
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

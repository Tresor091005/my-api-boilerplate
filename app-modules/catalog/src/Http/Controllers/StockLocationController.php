<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\StockLocationData;
use Lahatre\Catalog\Data\StockLocationFilterData;
use Lahatre\Catalog\Http\Requests\StockLocationFilterRequest;
use Lahatre\Catalog\Http\Requests\StockLocationRequest;
use Lahatre\Catalog\Http\Resources\StockLocationResource;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Services\StockLocationService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class StockLocationController
{
    public function __construct(
        private StockLocationService $stockLocationService,
    ) {}

    public function index(StockLocationFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', StockLocation::class);

        $locations = $this->stockLocationService->paginate(
            StockLocationFilterData::fromArray($request->validated()),
        );

        return ApiResponse::cursorPage($locations, StockLocationResource::class);
    }

    public function store(StockLocationRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', StockLocation::class);
        $location = $this->stockLocationService->create(
            StockLocationData::fromArray($request->validated()),
        );

        return ApiResponse::item($location, StockLocationResource::class, 201);
    }

    public function show(StockLocation $stockLocation): JsonResponse|Response
    {
        Gate::authorize('retrieve', $stockLocation);

        return ApiResponse::item($this->stockLocationService->retrieve($stockLocation), StockLocationResource::class);
    }

    public function update(StockLocationRequest $request, StockLocation $stockLocation): JsonResponse|Response
    {
        Gate::authorize('update', $stockLocation);
        $location = $this->stockLocationService->update(
            $stockLocation,
            StockLocationData::fromArray(
                $request->validated(),
                missingFields: ['name', 'is_active', 'address'],
            ),
        );

        return ApiResponse::item($location, StockLocationResource::class);
    }

    public function destroy(StockLocation $stockLocation): \Illuminate\Http\Response
    {
        Gate::authorize('delete', $stockLocation);
        $this->stockLocationService->delete($stockLocation);

        return ApiResponse::noContent();
    }
}

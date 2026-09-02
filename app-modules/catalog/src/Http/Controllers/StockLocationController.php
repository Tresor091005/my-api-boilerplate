<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\StockLocationData;
use Lahatre\Catalog\Data\StockLocationFilterData;
use Lahatre\Catalog\Http\Requests\StockLocationFilterRequest;
use Lahatre\Catalog\Http\Requests\StockLocationRequest;
use Lahatre\Catalog\Http\Resources\StockLocationCollection;
use Lahatre\Catalog\Http\Resources\StockLocationResource;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Services\StockLocationService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final class StockLocationController
{
    public function __construct(
        private StockLocationService $stockLocationService,
        private ResponseResponder $responseResponder,
    ) {}

    public function index(StockLocationFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', StockLocation::class);

        $locations = $this->stockLocationService->paginate(
            StockLocationFilterData::fromArray($request->validated()),
        );

        return $this->responseResponder->respond(
            fn (): JsonResource => StockLocationCollection::make($locations),
        );
    }

    public function store(StockLocationRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', StockLocation::class);
        $location = $this->stockLocationService->create(
            StockLocationData::fromArray($request->validated()),
        );

        return $this->responseResponder->respond(
            fn (): JsonResource => StockLocationResource::make($location),
            status: 201,
        );
    }

    public function show(StockLocation $stockLocation): JsonResponse|Response
    {
        Gate::authorize('retrieve', $stockLocation);

        return $this->responseResponder->respond(
            fn (): JsonResource => StockLocationResource::make(
                $this->stockLocationService->retrieve($stockLocation),
            ),
        );
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

        return $this->responseResponder->respond(
            fn (): JsonResource => StockLocationResource::make($location),
        );
    }

    public function destroy(StockLocation $stockLocation): Response
    {
        Gate::authorize('delete', $stockLocation);
        $this->stockLocationService->delete($stockLocation);

        return response()->noContent();
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\PriceLevelData;
use Lahatre\Catalog\Data\PriceLevelFilterData;
use Lahatre\Catalog\Http\Requests\PriceLevelCreateRequest;
use Lahatre\Catalog\Http\Requests\PriceLevelFilterRequest;
use Lahatre\Catalog\Http\Requests\PriceLevelUpdateRequest;
use Lahatre\Catalog\Http\Resources\PriceLevelResource;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Services\PriceLevelService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class PriceLevelController
{
    public function __construct(
        private PriceLevelService $priceLevelService,
    ) {}

    public function index(PriceLevelFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', PriceLevel::class);

        $priceLevels = $this->priceLevelService->paginate(
            PriceLevelFilterData::fromArray($request->validated()),
        );

        return ApiResponse::cursorPage($priceLevels, PriceLevelResource::class);
    }

    public function store(PriceLevelCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', PriceLevel::class);
        $priceLevel = $this->priceLevelService->create(PriceLevelData::fromArray($request->validated()));

        return ApiResponse::item($priceLevel, PriceLevelResource::class, 201);
    }

    public function show(PriceLevel $priceLevel): JsonResponse|Response
    {
        Gate::authorize('retrieve', $priceLevel);

        return ApiResponse::item($this->priceLevelService->retrieve($priceLevel), PriceLevelResource::class);
    }

    public function update(PriceLevelUpdateRequest $request, PriceLevel $priceLevel): JsonResponse|Response
    {
        Gate::authorize('update', $priceLevel);
        $priceLevel = $this->priceLevelService->update($priceLevel, PriceLevelData::fromArray($request->validated()));

        return ApiResponse::item($priceLevel, PriceLevelResource::class);
    }

    public function destroy(PriceLevel $priceLevel): \Illuminate\Http\Response
    {
        Gate::authorize('delete', $priceLevel);
        $this->priceLevelService->delete($priceLevel);

        return ApiResponse::noContent();
    }
}

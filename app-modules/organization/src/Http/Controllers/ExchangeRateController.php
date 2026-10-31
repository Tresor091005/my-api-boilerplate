<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Organization\Data\ExchangeRateData;
use Lahatre\Organization\Data\ExchangeRateFilterData;
use Lahatre\Organization\Data\ExchangeRateUpdateData;
use Lahatre\Organization\Http\Requests\ExchangeRateCreateRequest;
use Lahatre\Organization\Http\Requests\ExchangeRateFilterRequest;
use Lahatre\Organization\Http\Requests\ExchangeRateUpdateRequest;
use Lahatre\Organization\Http\Resources\ExchangeRateResource;
use Lahatre\Organization\Models\ExchangeRate;
use Lahatre\Organization\Services\ExchangeRateService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ExchangeRateController
{
    public function __construct(
        private ExchangeRateService $exchangeRateService,
    ) {}

    public function index(ExchangeRateFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', ExchangeRate::class);
        $rates = $this->exchangeRateService->paginate(ExchangeRateFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($rates, ExchangeRateResource::class);
    }

    public function store(ExchangeRateCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', ExchangeRate::class);
        $rate = $this->exchangeRateService->create(ExchangeRateData::fromArray($request->validated()));

        return ApiResponse::item($rate, ExchangeRateResource::class, 201);
    }

    public function show(ExchangeRate $exchangeRate): JsonResponse|Response
    {
        Gate::authorize('retrieve', $exchangeRate);
        $rate = $this->exchangeRateService->retrieve($exchangeRate);

        return ApiResponse::item($rate, ExchangeRateResource::class);
    }

    public function update(ExchangeRateUpdateRequest $request, ExchangeRate $exchangeRate): JsonResponse|Response
    {
        Gate::authorize('update', $exchangeRate);
        $rate = $this->exchangeRateService->update($exchangeRate, ExchangeRateUpdateData::fromArray($request->validated()));

        return ApiResponse::item($rate, ExchangeRateResource::class);
    }

    public function destroy(ExchangeRate $exchangeRate): \Illuminate\Http\Response
    {
        Gate::authorize('delete', $exchangeRate);
        $this->exchangeRateService->delete($exchangeRate);

        return ApiResponse::noContent();
    }
}

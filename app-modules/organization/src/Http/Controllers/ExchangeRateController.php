<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Organization\Data\ExchangeRateData;
use Lahatre\Organization\Data\ExchangeRateFilterData;
use Lahatre\Organization\Data\ExchangeRateUpdateData;
use Lahatre\Organization\Http\Requests\ExchangeRateCreateRequest;
use Lahatre\Organization\Http\Requests\ExchangeRateFilterRequest;
use Lahatre\Organization\Http\Requests\ExchangeRateUpdateRequest;
use Lahatre\Organization\Http\Resources\ExchangeRateCollection;
use Lahatre\Organization\Http\Resources\ExchangeRateResource;
use Lahatre\Organization\Models\ExchangeRate;
use Lahatre\Organization\Services\ExchangeRateService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class ExchangeRateController
{
    public function __construct(
        private ExchangeRateService $exchangeRateService,
        private ResponseResponder $responseResponder,
    ) {}

    public function index(ExchangeRateFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', ExchangeRate::class);
        $rates = $this->exchangeRateService->paginate(ExchangeRateFilterData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => ExchangeRateCollection::make($rates));
    }

    public function store(ExchangeRateCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', ExchangeRate::class);
        $rate = $this->exchangeRateService->create(ExchangeRateData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => ExchangeRateResource::make($rate), status: 201);
    }

    public function show(ExchangeRate $exchangeRate): JsonResponse|Response
    {
        Gate::authorize('retrieve', $exchangeRate);
        $rate = $this->exchangeRateService->retrieve($exchangeRate);

        return $this->responseResponder->respond(fn (): JsonResource => ExchangeRateResource::make($rate));
    }

    public function update(ExchangeRateUpdateRequest $request, ExchangeRate $exchangeRate): JsonResponse|Response
    {
        Gate::authorize('update', $exchangeRate);
        $rate = $this->exchangeRateService->update($exchangeRate, ExchangeRateUpdateData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => ExchangeRateResource::make($rate));
    }

    public function destroy(ExchangeRate $exchangeRate): Response
    {
        Gate::authorize('delete', $exchangeRate);
        $this->exchangeRateService->delete($exchangeRate);

        return response()->noContent();
    }
}

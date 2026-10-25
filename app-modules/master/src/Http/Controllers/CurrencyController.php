<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Master\Data\CurrencyFilterData;
use Lahatre\Master\Http\Requests\CurrencyFilterRequest;
use Lahatre\Master\Http\Resources\CurrencyResource;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Services\CurrencyService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class CurrencyController
{
    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    public function index(CurrencyFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Currency::class);

        $filters = CurrencyFilterData::fromArray($request->validated());

        $response = $this->currencyService->paginate($filters);

        return ApiResponse::cursorPage($response, CurrencyResource::class);
    }
}

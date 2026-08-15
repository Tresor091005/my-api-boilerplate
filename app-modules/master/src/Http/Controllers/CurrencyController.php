<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Master\Data\CurrencyFilterData;
use Lahatre\Master\Http\Requests\CurrencyFilterRequest;
use Lahatre\Master\Http\Resources\CurrencyCollection;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Services\CurrencyService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class CurrencyController
{
    public function __construct(
        protected CurrencyService $currencyService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(CurrencyFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Currency::class);

        $filters = CurrencyFilterData::fromArray($request->validated());

        $response = $this->currencyService->paginate($filters);

        return $this->responseResponder->respond(fn (): JsonResource => CurrencyCollection::make($response));
    }
}

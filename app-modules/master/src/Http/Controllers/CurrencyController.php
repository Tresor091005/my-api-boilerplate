<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Lahatre\Master\Data\CurrencyFilterData;
use Lahatre\Master\Http\Requests\CurrencyFilterRequest;
use Lahatre\Master\Http\Resources\CurrencyCollection;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Services\CurrencyService;

class CurrencyController
{
    public function __construct(
        protected CurrencyService $currencyService
    ) {}

    public function index(CurrencyFilterRequest $request): CurrencyCollection
    {
        Gate::authorize('list', Currency::class);

        $filters = CurrencyFilterData::fromArray($request->validated());

        return $this->currencyService->list($filters);
    }
}

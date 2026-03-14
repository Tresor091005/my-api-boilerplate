<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Master\DTO\CurrencyFilterDTO;
use Lahatre\Master\Http\Resources\CurrencyCollection;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Services\CurrencyService;

class CurrencyController
{
    public function __construct(
        protected CurrencyService $currencyService
    ) {}

    public function index(Request $request): CurrencyCollection
    {
        Gate::authorize('list', Currency::class);

        $filters = CurrencyFilterDTO::fromRequest($request);

        return $this->currencyService->list($filters);
    }
}

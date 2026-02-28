<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\DTO\CurrencyFilterDTO;
use Lahatre\Catalog\Http\Resources\CurrencyCollection;
use Lahatre\Catalog\Models\Currency;
use Lahatre\Catalog\Services\CurrencyService;

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

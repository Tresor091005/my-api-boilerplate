<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Lahatre\Master\Data\CurrencyFilterData;
use Lahatre\Master\Http\Resources\CurrencyCollection;
use Lahatre\Master\Models\Currency;
use Lahatre\Shared\Contracts\Services\StandaloneService;

class CurrencyService implements StandaloneService
{
    public function list(CurrencyFilterData $filters): CurrencyCollection
    {
        $query = Currency::query();

        if ($filters->code) {
            $query->where('code', 'like', "$filters->code%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "$filters->name%");
        }

        $currencies = stableCursorPaginate($query, $filters);

        return CurrencyCollection::make($currencies);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Lahatre\Master\Data\CurrencyFilterData;
use Lahatre\Master\Models\Currency;

class CurrencyService
{
    public function paginate(CurrencyFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate($this->currenciesQuery($filters), $filters);
    }

    /** @return Builder<Currency> */
    private function currenciesQuery(CurrencyFilterData $filters): Builder
    {
        $query = Currency::query();

        if ($filters->code) {
            $query->where('code', 'like', "$filters->code%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "$filters->name%");
        }

        return $query;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Master\Services;

use Lahatre\Master\DTO\CurrencyFilterDTO;
use Lahatre\Master\Http\Resources\CurrencyCollection;
use Lahatre\Master\Models\Currency;
use Lahatre\Shared\Contracts\Services\StandaloneService;

class CurrencyService implements StandaloneService
{
    public function list(CurrencyFilterDTO $filters): CurrencyCollection
    {
        $query = Currency::query();

        if ($filters->code) {
            $query->where('code', 'like', "$filters->code}%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "$filters->name}%");
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);

        $currencies = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return CurrencyCollection::make($currencies);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\CategoryAssertion;
use Lahatre\Catalog\DTO\CategoryDTO;
use Lahatre\Catalog\DTO\CategoryFilterDTO;
use Lahatre\Catalog\DTO\CurrencyFilterDTO;
use Lahatre\Catalog\Http\Resources\CategoryCollection;
use Lahatre\Catalog\Http\Resources\CategoryResource;
use Lahatre\Catalog\Http\Resources\CurrencyCollection;
use Lahatre\Catalog\Http\Resources\ProductResource;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Models\Currency;
use Lahatre\Shared\Support\HandleGenerator;

class CurrencyService
{
    public function list(CurrencyFilterDTO $filters): CurrencyCollection
    {
        $query = Currency::query();

        if ($filters->code) {
            $query->where('code', 'like', "%{$filters->code}%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "%{$filters->name}%");
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);

        $currencies = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return CurrencyCollection::make($currencies);
    }
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Models\Category;

it('applies the standard cursor filter quartet with a deterministic id tie breaker', function (): void {
    $query = Category::query();
    $filters = (object) [
        'sortBy'    => 'name',
        'sortOrder' => 'desc',
        'perPage'   => 15,
        'cursor'    => null,
    ];

    stableCursorPaginate($query, $filters);

    expect($query->getQuery()->orders)->toBe([
        ['column' => 'name', 'direction' => 'desc'],
        ['column' => 'id', 'direction' => 'desc'],
    ]);
});

it('does not append the tie breaker twice when id is already ordered', function (): void {
    $query = Category::query();
    $filters = (object) [
        'sortBy'    => 'id',
        'sortOrder' => 'asc',
        'perPage'   => 15,
        'cursor'    => null,
    ];

    stableCursorPaginate($query, $filters);

    expect($query->getQuery()->orders)->toBe([
        ['column' => 'id', 'direction' => 'asc'],
    ]);
});

it('supports explicit tie breaker columns on aliased queries', function (): void {
    $query = DB::table('inventory_stocks as stocks');
    $filters = (object) [
        'sortBy'    => 'stocks.expiration_date',
        'sortOrder' => 'asc',
        'perPage'   => 15,
        'cursor'    => null,
    ];

    stableCursorPaginate($query, $filters, tieBreakerColumn: 'stocks.id');

    expect($query->orders)->toBe([
        ['column' => 'stocks.expiration_date', 'direction' => 'asc'],
        ['column' => 'stocks.id', 'direction' => 'asc'],
    ]);
});

it('fails fast when the filter object does not expose the cursor quartet', function (): void {
    $query = Category::query();
    $filters = (object) ['perPage' => 15];

    expect(fn () => stableCursorPaginate($query, $filters))
        ->toThrow(InvalidArgumentException::class);
});

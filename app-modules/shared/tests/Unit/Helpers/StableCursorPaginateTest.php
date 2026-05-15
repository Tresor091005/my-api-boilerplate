<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Models\Category;

it('applies the standard cursor filter quartet with a deterministic id tie breaker', function (): void {
    $query = Category::query();
    $filters = (object) [
        'sort_by'    => 'name',
        'sort_order' => 'desc',
        'per_page'   => 15,
        'cursor'     => null,
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
        'sort_by'    => 'id',
        'sort_order' => 'asc',
        'per_page'   => 15,
        'cursor'     => null,
    ];

    stableCursorPaginate($query, $filters);

    expect($query->getQuery()->orders)->toBe([
        ['column' => 'id', 'direction' => 'asc'],
    ]);
});

it('supports explicit tie breaker columns on aliased queries', function (): void {
    $query = DB::table('inventory_stocks as stocks');
    $filters = (object) [
        'sort_by'    => 'stocks.expiration_date',
        'sort_order' => 'asc',
        'per_page'   => 15,
        'cursor'     => null,
    ];

    stableCursorPaginate($query, $filters, tieBreakerColumn: 'stocks.id');

    expect($query->orders)->toBe([
        ['column' => 'stocks.expiration_date', 'direction' => 'asc'],
        ['column' => 'stocks.id', 'direction' => 'asc'],
    ]);
});

it('fails fast when the filter object does not expose the cursor quartet', function (): void {
    $query = Category::query();
    $filters = (object) ['per_page' => 15];

    expect(fn () => stableCursorPaginate($query, $filters))
        ->toThrow(InvalidArgumentException::class);
});

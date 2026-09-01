<?php

declare(strict_types=1);

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\Category;

uses(RefreshDatabase::class);

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

it('moves an existing tie breaker to the final order position', function (): void {
    $query = Category::query()->orderBy('id', 'desc');
    $filters = (object) [
        'sortBy'    => 'name',
        'sortOrder' => 'asc',
        'perPage'   => 15,
        'cursor'    => null,
    ];

    stableCursorPaginate($query, $filters);

    expect($query->getQuery()->orders)->toBe([
        ['column' => 'name', 'direction' => 'asc'],
        ['column' => 'id', 'direction' => 'asc'],
    ]);
});

it('clamps the page size and rejects an invalid sort direction', function (): void {
    $filters = (object) [
        'sortBy'    => 'name',
        'sortOrder' => 'asc',
        'perPage'   => 1000,
        'cursor'    => null,
    ];

    $paginator = stableCursorPaginate(Category::query(), $filters);

    expect($paginator->perPage())->toBe(100);

    $filters->sortOrder = 'sideways';

    expect(fn (): CursorPaginator => stableCursorPaginate(Category::query(), $filters))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps duplicate sort values on separate cursor pages without gaps or duplicates', function (): void {
    $organizationId = (string) Str::uuid7();
    DB::table('organization_organizations')->insert([
        'id'                       => $organizationId,
        'name'                     => 'Cursor Test Organization',
        'functional_currency_code' => 'XOF',
        'created_at'               => now(),
        'updated_at'               => now(),
    ]);

    $categories = collect(range(1, 5))->map(function (int $index) use ($organizationId): Category {
        return Category::factory()->create([
            'organization_id' => $organizationId,
            'name'            => 'Duplicate Name',
            'handle'          => "duplicate-name-{$index}",
        ]);
    });
    $filters = (object) [
        'sortBy'    => 'name',
        'sortOrder' => 'asc',
        'perPage'   => 2,
        'cursor'    => null,
    ];

    $seenIds = collect();

    do {
        $page = stableCursorPaginate(Category::query()->where('organization_id', $organizationId), $filters);
        $seenIds = $seenIds->merge(collect($page->items())->pluck('id'));
        $filters->cursor = $page->nextCursor()?->encode();
    } while ($filters->cursor !== null);

    expect($seenIds->count())->toBe(5)
        ->and($seenIds->unique()->values()->all())->toEqual($categories->pluck('id')->sort()->values()->all());
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

    expect(fn (): CursorPaginator => stableCursorPaginate($query, $filters))
        ->toThrow(InvalidArgumentException::class);
});

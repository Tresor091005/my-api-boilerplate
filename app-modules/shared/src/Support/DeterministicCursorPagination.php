<?php

declare(strict_types=1);

namespace Lahatre\Shared\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

class DeterministicCursorPagination
{
    private const int MIN_PER_PAGE = 1;

    private const int MAX_PER_PAGE = 100;

    /**
     * Apply the standard sortable cursor filter quartet:
     * sortBy, sortOrder, perPage, cursor.
     *
     * @throws InvalidArgumentException
     */
    public static function paginate(
        EloquentBuilder|QueryBuilder|Relation $query,
        object $filters,
        array $columns = ['*'],
        string $tieBreakerColumn = 'id',
        string $cursorName = 'cursor'
    ): CursorPaginator {
        self::assertFilterContract($filters);
        $sortOrder = self::normalizeSortOrder($filters->sortOrder);
        $perPage = max(self::MIN_PER_PAGE, min(self::MAX_PER_PAGE, $filters->perPage));

        $query->orderBy($filters->sortBy, $sortOrder);
        self::appendTieBreakerAsFinalOrder($query, $tieBreakerColumn, $sortOrder);

        return $query->cursorPaginate($perPage, $columns, $cursorName, $filters->cursor);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected static function assertFilterContract(object $filters): void
    {
        foreach (['sortBy', 'sortOrder', 'perPage', 'cursor'] as $property) {
            if (!property_exists($filters, $property)) {
                throw new InvalidArgumentException(sprintf(
                    'DeterministicCursorPagination expects a filter object with %s.',
                    implode(', ', ['sortBy', 'sortOrder', 'perPage', 'cursor'])
                ));
            }
        }

        if (!is_string($filters->sortBy) || !is_string($filters->sortOrder) || !is_int($filters->perPage)) {
            throw new InvalidArgumentException(
                'DeterministicCursorPagination expects sortBy:string, sortOrder:string, and perPage:int.'
            );
        }

        if (trim($filters->sortBy) === '') {
            throw new InvalidArgumentException(
                'DeterministicCursorPagination expects sortBy to be a non-empty string.'
            );
        }

        if ($filters->cursor !== null && !is_string($filters->cursor)) {
            throw new InvalidArgumentException(
                'DeterministicCursorPagination expects cursor to be null or string.'
            );
        }
    }

    protected static function normalizeSortOrder(string $sortOrder): string
    {
        $normalizedSortOrder = strtolower($sortOrder);

        if (!in_array($normalizedSortOrder, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException(
                'DeterministicCursorPagination expects sortOrder to be asc or desc.'
            );
        }

        return $normalizedSortOrder;
    }

    protected static function appendTieBreakerAsFinalOrder(
        EloquentBuilder|QueryBuilder|Relation $query,
        string $column,
        string $direction,
    ): void {
        if (trim($column) === '') {
            throw new InvalidArgumentException(
                'DeterministicCursorPagination expects tieBreakerColumn to be a non-empty string.'
            );
        }

        $baseQuery = self::toBaseQuery($query);
        $normalizedColumn = self::normalizeColumn($column);
        $baseQuery->orders = array_values(array_filter(
            self::orders($query),
            fn (array $order): bool => !is_string($order['column'] ?? null)
                || self::normalizeColumn($order['column']) !== $normalizedColumn,
        ));

        $query->orderBy($column, $direction);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function orders(EloquentBuilder|QueryBuilder|Relation $query): array
    {
        return self::toBaseQuery($query)->orders ?? [];
    }

    protected static function toBaseQuery(EloquentBuilder|QueryBuilder|Relation $query): QueryBuilder
    {
        return match (true) {
            $query instanceof Relation        => $query->getQuery()->getQuery(),
            $query instanceof EloquentBuilder => $query->getQuery(),
            default                           => $query,
        };
    }

    protected static function normalizeColumn(string $column): string
    {
        return strtolower(trim(str_replace(['"', '`'], '', $column)));
    }
}

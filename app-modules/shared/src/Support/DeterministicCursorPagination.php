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

        $query->orderBy($filters->sortBy, $filters->sortOrder);

        if (!self::hasBasicOrderBy($query, $tieBreakerColumn)) {
            $query->orderBy($tieBreakerColumn, self::lastDirection($query, $filters->sortOrder));
        }

        return $query->cursorPaginate($filters->perPage, $columns, $cursorName, $filters->cursor);
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

        if ($filters->cursor !== null && !is_string($filters->cursor)) {
            throw new InvalidArgumentException(
                'DeterministicCursorPagination expects cursor to be null or string.'
            );
        }
    }

    protected static function hasBasicOrderBy(
        EloquentBuilder|QueryBuilder|Relation $query,
        string $column
    ): bool {
        $normalizedColumn = self::normalizeColumn($column);

        foreach (self::orders($query) as $order) {
            if (($order['type'] ?? 'Basic') !== 'Basic') {
                continue;
            }

            $orderedColumn = $order['column'] ?? null;

            if (!is_string($orderedColumn)) {
                continue;
            }

            if (self::normalizeColumn($orderedColumn) === $normalizedColumn) {
                return true;
            }
        }

        return false;
    }

    protected static function lastDirection(
        EloquentBuilder|QueryBuilder|Relation $query,
        string $fallback
    ): string {
        foreach (array_reverse(self::orders($query)) as $order) {
            $direction = strtolower((string) ($order['direction'] ?? ''));

            if (in_array($direction, ['asc', 'desc'], true)) {
                return $direction;
            }
        }

        return strtolower($fallback);
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

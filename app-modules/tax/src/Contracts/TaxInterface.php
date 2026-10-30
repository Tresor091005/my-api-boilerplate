<?php

declare(strict_types=1);

namespace Lahatre\Tax\Contracts;

use Illuminate\Support\Collection;
use Lahatre\Tax\Data\TaxableLineData;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\ViewData\TaxResultViewData;

/**
 * Public API of the tax engine for the current organization.
 */
interface TaxInterface
{
    /**
     * Checks that a profile can be assigned to records measured in the given
     * unit groups: it exists in the organization, is active, and each of its
     * applicable per-unit taxes uses a unit of every group. Call it inside the
     * assigning transaction.
     *
     * @param  Collection<int, string>  $unitGroupIds
     *
     * @throws TaxException
     */
    public function assertProfileAssignable(string $taxProfileId, Collection $unitGroupIds): void;

    /**
     * Calculates one line; see resolveMany().
     *
     * @throws TaxException
     */
    public function resolve(TaxableLineData $line): TaxResultViewData;

    /**
     * Calculates many lines with a fixed number of queries whatever their
     * count. A line without a profile has no tax. Nothing is persisted: a
     * caller freezes `TaxResultViewData::toArray()` when its document is
     * confirmed.
     *
     * @template TKey of array-key
     *
     * @param  Collection<TKey, TaxableLineData>  $lines
     *
     * @throws TaxException
     *
     * @return Collection<TKey, TaxResultViewData> keyed like $lines
     */
    public function resolveMany(Collection $lines): Collection;
}

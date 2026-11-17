<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Support\Collection;

/**
 * What inventory holds for some items at some locations, read without locking: a caller checks
 * a request with it, and recording the transaction checks again under lock. Items and locations
 * are named by their owners' ids; quantities are in each item's base unit.
 */
final readonly class StockAvailabilityViewData
{
    /**
     * @param  Collection<string, bool>  $trackedItems  whether each known item has its stock tracked
     * @param  Collection<string, bool>  $activeLocations  whether each known location is active
     * @param  Collection<string, StockLotViewData>  $lots  the open lots, keyed by stock id
     */
    public function __construct(
        public Collection $trackedItems,
        public Collection $activeLocations,
        public Collection $lots,
    ) {}

    public function available(string $itemId, string $locationId): int
    {
        return (int) $this->lots
            ->filter(fn (StockLotViewData $lot): bool => $lot->itemId === $itemId && $lot->locationId === $locationId)
            ->sum('remaining');
    }

    public function isTracked(string $itemId): bool
    {
        return $this->trackedItems->get($itemId, false);
    }
}

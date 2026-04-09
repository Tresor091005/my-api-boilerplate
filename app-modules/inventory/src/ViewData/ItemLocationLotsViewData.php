<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Support\Collection;

readonly class ItemLocationLotsViewData
{
    /**
     * @param  Collection<int, AvailableLotViewData>  $lots
     */
    public function __construct(
        public string $itemId,
        public string $locationId,
        public string $deductionStrategy,
        public int $totalRemaining,
        public string $unitCode,
        public Collection $lots,
    ) {}
}

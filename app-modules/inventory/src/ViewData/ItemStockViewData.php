<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Support\Collection;

readonly class ItemStockViewData
{
    /**
     * @param  Collection<int, ItemStockLocationViewData>  $locations
     */
    public function __construct(
        public string $itemId,
        public int $totalRemaining,
        public string $unitCode,
        public Collection $locations,
    ) {}
}

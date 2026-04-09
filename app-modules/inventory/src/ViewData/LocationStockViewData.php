<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Support\Collection;

readonly class LocationStockViewData
{
    /**
     * @param  Collection<int, LocationStockItemViewData>  $items
     */
    public function __construct(
        public string $locationId,
        public Collection $items,
    ) {}
}

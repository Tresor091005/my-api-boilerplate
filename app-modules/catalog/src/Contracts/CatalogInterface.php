<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Contracts;

use Illuminate\Support\Collection;
use Lahatre\Catalog\Data\SellableItemQueryData;
use Lahatre\Catalog\ViewData\SellableItemViewData;

interface CatalogInterface
{
    /**
     * What a sale needs per (item, unit, price level); a missing item is absent,
     * a missing level leaves level and price empty, and the caller decides what to refuse.
     *
     * @param  Collection<int, SellableItemQueryData>  $requests
     * @return Collection<string, SellableItemViewData> keyed by "{catalog_item_id}:{unit_code}:{price_level_id}"
     */
    public function sellableItems(string $organizationId, Collection $requests): Collection;
}

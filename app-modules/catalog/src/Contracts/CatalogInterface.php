<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Contracts;

use Illuminate\Support\Collection;
use Lahatre\Catalog\Data\SellableItemQueryData;
use Lahatre\Catalog\ViewData\SellableItemViewData;

/**
 * Public API of the catalog.
 */
interface CatalogInterface
{
    /**
     * What a sale needs to know about each (item, unit, price level) it asks
     * for: the item, its tax profile, the unit, the level and the price at it.
     * Requests may name different levels. It only reads, with a number of
     * queries that does not depend on the number of requests. An item that
     * does not exist in the organization or is deleted is absent from the
     * result; a level that does not exist or is deleted leaves the level and
     * the price empty. The caller decides what to refuse.
     *
     * @param  Collection<int, SellableItemQueryData>  $requests
     * @return Collection<string, SellableItemViewData> keyed by "{catalog_item_id}:{unit_code}:{price_level_id}"
     */
    public function sellableItems(string $organizationId, Collection $requests): Collection;
}

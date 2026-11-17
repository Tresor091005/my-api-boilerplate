<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Contracts;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Lahatre\Catalog\Data\SellableItemQueryData;
use Lahatre\Catalog\Models\StockLocation;
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

    /**
     * The customer hold location of an active warehouse, created the first time it is needed.
     * Runs inside the caller's transaction.
     *
     * @throws ModelNotFoundException<StockLocation> If the warehouse is not an active one of the organization.
     */
    public function customerHoldLocation(string $organizationId, string $warehouseId): StockLocation;
}

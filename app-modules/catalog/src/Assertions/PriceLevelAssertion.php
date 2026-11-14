<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\PriceLevelException;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;

final readonly class PriceLevelAssertion
{
    /**
     * Assert that no item is still priced at the level.
     *
     * The caller holds a lock on the level, so no price can be added to it meanwhile.
     *
     * @throws PriceLevelException If an item still has a price at this level.
     */
    public function assertCanBeDeleted(PriceLevel $priceLevel): void
    {
        $isUsed = CatalogItemPrice::query()
            ->where('organization_id', $priceLevel->organization_id)
            ->where('price_level_id', $priceLevel->id)
            ->exists();

        if ($isUsed) {
            throw PriceLevelException::inUse($priceLevel);
        }
    }
}

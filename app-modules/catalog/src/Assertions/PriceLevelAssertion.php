<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Contracts\PriceLevelUsageInterface;
use Lahatre\Catalog\Exceptions\PriceLevelException;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;

final readonly class PriceLevelAssertion
{
    public function __construct(
        private PriceLevelUsageInterface $priceLevelUsage,
    ) {}

    /**
     * Assert that the level is not the built-in one, that no item is still
     * priced at it and that no other module's record, such as a customer,
     * sells at it.
     *
     * The caller holds a lock on the level, so no price can be added to it meanwhile.
     *
     * @throws PriceLevelException If the level is the built-in one, an item has a price at it or a record uses it.
     */
    public function assertCanBeDeleted(PriceLevel $priceLevel): void
    {
        if ($priceLevel->is_builtin) {
            throw PriceLevelException::builtinCannotBeDeleted($priceLevel);
        }

        $isUsed = CatalogItemPrice::query()
            ->where('organization_id', $priceLevel->organization_id)
            ->where('price_level_id', $priceLevel->id)
            ->exists();

        if ($isUsed || $this->priceLevelUsage->isUsed($priceLevel->organization_id, $priceLevel->id)) {
            throw PriceLevelException::inUse($priceLevel);
        }
    }
}

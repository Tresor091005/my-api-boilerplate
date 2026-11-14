<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Contracts;

/**
 * Implemented by a module whose records reference price levels, so a level
 * they use is not deleted, without the catalog depending on that module.
 * Such a module tags its implementation with SOURCES_TAG in its provider's
 * boot(); the catalog asks every tagged source.
 */
interface PriceLevelUsageInterface
{
    public const string SOURCES_TAG = 'catalog.price-level-usage-sources';

    /** Whether a non-deleted record of the organization uses the level. */
    public function isUsed(string $organizationId, string $priceLevelId): bool;
}

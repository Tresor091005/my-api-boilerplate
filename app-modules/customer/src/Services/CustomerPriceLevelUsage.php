<?php

declare(strict_types=1);

namespace Lahatre\Customer\Services;

use Lahatre\Catalog\Contracts\PriceLevelUsageInterface;
use Lahatre\Customer\Models\Customer;

/**
 * A customer sells only at its price level, so that level must not be deleted.
 */
final class CustomerPriceLevelUsage implements PriceLevelUsageInterface
{
    public function isUsed(string $organizationId, string $priceLevelId): bool
    {
        return Customer::query()
            ->where('organization_id', $organizationId)
            ->where('price_level_id', $priceLevelId)
            ->exists();
    }
}

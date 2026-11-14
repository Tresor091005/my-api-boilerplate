<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Lahatre\Catalog\Contracts\PriceLevelUsageInterface;

/**
 * Asks every module tagged as a source of price level usage. Without a
 * source, no level is used outside the catalog.
 */
final readonly class CombinedPriceLevelUsage implements PriceLevelUsageInterface
{
    /** @param iterable<PriceLevelUsageInterface> $sources */
    public function __construct(
        private iterable $sources,
    ) {}

    public function isUsed(string $organizationId, string $priceLevelId): bool
    {
        foreach ($this->sources as $source) {
            if ($source->isUsed($organizationId, $priceLevelId)) {
                return true;
            }
        }

        return false;
    }
}

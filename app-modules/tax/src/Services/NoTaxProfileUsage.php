<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Illuminate\Support\Collection;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;

/**
 * Default binding while no module assigns tax profiles.
 */
final readonly class NoTaxProfileUsage implements TaxProfileUsageInterface
{
    public function unitGroupIdsByProfile(string $organizationId, Collection $taxProfileIds): Collection
    {
        return collect();
    }
}

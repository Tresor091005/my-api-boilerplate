<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Illuminate\Support\Collection;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;

/**
 * Asks every module tagged as a source of profile usage and merges the
 * answers, so no module can hide another one's records. Without a source,
 * no profile is used.
 */
final readonly class CombinedTaxProfileUsage implements TaxProfileUsageInterface
{
    /** @param iterable<TaxProfileUsageInterface> $sources */
    public function __construct(
        private iterable $sources,
    ) {}

    public function unitGroupIdsByProfile(string $organizationId, Collection $taxProfileIds): Collection
    {
        $merged = collect();

        foreach ($this->sources as $source) {
            foreach ($source->unitGroupIdsByProfile($organizationId, $taxProfileIds) as $profileId => $unitGroupIds) {
                $merged[$profileId] = $merged->get($profileId, collect())->merge($unitGroupIds)->unique()->values();
            }
        }

        return $merged;
    }

    public function includedTaxIdSetsByProfile(string $organizationId, Collection $taxProfileIds): Collection
    {
        $merged = collect();

        foreach ($this->sources as $source) {
            foreach ($source->includedTaxIdSetsByProfile($organizationId, $taxProfileIds) as $profileId => $taxIdSets) {
                $merged[$profileId] = $merged->get($profileId, collect())
                    ->merge($taxIdSets)
                    ->unique(fn (array $taxIds): string => collect($taxIds)->sort()->implode(','))
                    ->values();
            }
        }

        return $merged;
    }
}

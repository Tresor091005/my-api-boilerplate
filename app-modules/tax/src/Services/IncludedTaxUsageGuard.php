<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Illuminate\Support\Collection;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Services\Engine\CompiledTaxProfile;
use Lahatre\Tax\Services\Engine\IncludedTaxRules;
use Lahatre\Tax\Services\Engine\TaxProfileCompiler;

/**
 * Keeps true what records already rely on when the tax configuration changes:
 * the taxes their amounts contain, such as the prices of items, must stay
 * taxes that can be taken out of an amount. A change that breaks it is refused
 * where it is made, not later when the item is sold.
 */
final readonly class IncludedTaxUsageGuard
{
    public function __construct(
        private TaxProfileUsageInterface $profileUsage,
        private TaxProfileCompiler $profileCompiler,
        private IncludedTaxRules $includedTaxRules,
    ) {}

    /**
     * Checks the profiles as they stand after the writes of the caller, inside
     * its transaction and under its configuration lock, against the taxes the
     * records using them already contain. A tax that left a profile is no
     * longer contained and is not checked.
     *
     * @param  Collection<int, string>  $taxProfileIds  the profiles the write changed
     *
     * @throws TaxException If amounts of records using a profile contain taxes they can no longer contain.
     */
    public function assertIncludedTaxesStillHold(string $organizationId, Collection $taxProfileIds): void
    {
        if ($taxProfileIds->isEmpty()) {
            return;
        }

        $taxIdSetsByProfile = $this->profileUsage->includedTaxIdSetsByProfile($organizationId, $taxProfileIds);

        if ($taxIdSetsByProfile->isEmpty()) {
            return;
        }

        $profiles = $this->profileCompiler->compile($organizationId, $taxIdSetsByProfile->keys()->values());

        foreach ($taxIdSetsByProfile as $profileId => $taxIdSets) {
            $profile = $profiles->get($profileId);

            if (!$profile instanceof CompiledTaxProfile) {
                continue;
            }

            foreach ($taxIdSets as $taxIds) {
                try {
                    $this->includedTaxRules->includedTaxes($profile, $taxIds);
                } catch (TaxException $obstacle) {
                    throw TaxException::includedTaxesInUse($profile->code, $obstacle);
                }
            }
        }
    }
}

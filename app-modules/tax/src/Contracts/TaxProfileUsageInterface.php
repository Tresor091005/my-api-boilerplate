<?php

declare(strict_types=1);

namespace Lahatre\Tax\Contracts;

use Illuminate\Support\Collection;

/**
 * Implemented by a module whose records reference tax profiles, so tax and
 * profile changes can be checked against them without the tax module
 * depending on it.
 * Such a module tags its implementation with SOURCES_TAG in its provider's
 * boot(); the tax module merges every tagged source.
 */
interface TaxProfileUsageInterface
{
    public const string SOURCES_TAG = 'tax.profile-usage-sources';

    /**
     * @param  Collection<int, string>  $taxProfileIds
     * @return Collection<string, Collection<int, string>> unit group ids of the non-deleted records using each profile, keyed by profile id; unused profiles are absent
     */
    public function unitGroupIdsByProfile(string $organizationId, Collection $taxProfileIds): Collection;

    /**
     * @param  Collection<int, string>  $taxProfileIds
     * @return Collection<string, Collection<int, list<string>>> the distinct sets of taxes that amounts of the non-deleted records using each profile already contain, such as the prices of items, keyed by profile id; profiles without such an amount are absent
     */
    public function includedTaxIdSetsByProfile(string $organizationId, Collection $taxProfileIds): Collection;
}

<?php

declare(strict_types=1);

namespace Lahatre\Tax\Contracts;

use Illuminate\Support\Collection;

/**
 * Implemented by a module whose records reference tax profiles, so profile
 * changes can be checked against them without the tax module depending on it.
 */
interface TaxProfileUsageInterface
{
    /**
     * @param  Collection<int, string>  $taxProfileIds
     * @return Collection<string, Collection<int, string>> unit group ids of the non-deleted records using each profile, keyed by profile id; unused profiles are absent
     */
    public function unitGroupIdsByProfile(string $organizationId, Collection $taxProfileIds): Collection;
}

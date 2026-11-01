<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Collection;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;

final class CatalogTaxProfileUsage implements TaxProfileUsageInterface
{
    public function unitGroupIdsByProfile(string $organizationId, Collection $taxProfileIds): Collection
    {
        if ($taxProfileIds->isEmpty()) {
            return collect();
        }

        /** @var Collection<string, Collection<int, string>> */
        return CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->whereIn('tax_profile_id', $taxProfileIds->all())
            ->distinct()
            ->get(['tax_profile_id', 'unit_group_id'])
            ->groupBy('tax_profile_id')
            ->map(fn (Collection $items): Collection => $items->pluck('unit_group_id')->values());
    }
}

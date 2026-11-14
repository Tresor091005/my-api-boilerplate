<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;
use stdClass;

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

    /**
     * The taxes each price of the items contains, reduced by the database to
     * the distinct sets of each profile: a catalog has many prices and few
     * different sets.
     */
    public function includedTaxIdSetsByProfile(string $organizationId, Collection $taxProfileIds): Collection
    {
        if ($taxProfileIds->isEmpty()) {
            return collect();
        }

        $taxIdsByPrice = DB::table('catalog_item_price_taxes as price_taxes')
            ->join('catalog_item_prices as prices', function ($join): void {
                $join->on('prices.organization_id', '=', 'price_taxes.organization_id')
                    ->on('prices.id', '=', 'price_taxes.catalog_item_price_id');
            })
            ->join('catalog_items as items', function ($join): void {
                $join->on('items.organization_id', '=', 'prices.organization_id')
                    ->on('items.id', '=', 'prices.catalog_item_id');
            })
            ->where('price_taxes.organization_id', $organizationId)
            ->whereNull('prices.deleted_at')
            ->whereNull('items.deleted_at')
            ->whereIn('items.tax_profile_id', $taxProfileIds->all())
            ->groupBy('items.tax_profile_id', 'prices.id')
            ->selectRaw("items.tax_profile_id, string_agg(price_taxes.tax_id::text, ',' ORDER BY price_taxes.tax_id) as tax_ids");

        /** @var Collection<string, Collection<int, list<string>>> */
        return DB::query()
            ->fromSub($taxIdsByPrice, 'price_tax_sets')
            ->distinct()
            ->get(['tax_profile_id', 'tax_ids'])
            ->groupBy('tax_profile_id')
            ->map(fn (Collection $sets): Collection => $sets->map(fn (stdClass $set): array => explode(',', (string) $set->tax_ids))->values());
    }
}

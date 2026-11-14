<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Organization\Contracts\OrganizationInterface;

/**
 * Demo prices: a retail and a wholesale level in the functional currency, and
 * both prices on the base unit of every catalog item. Amounts are in minor units.
 */
final class PriceSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = currentOrganizationId();
        $master = app(MasterInterface::class);

        $currencyCode = app(OrganizationInterface::class)->functionalCurrency($organizationId)->code;
        $retail = PriceLevel::query()->firstOrCreate(['organization_id' => $organizationId, 'currency_code' => $currencyCode, 'name' => 'retail']);
        $wholesale = PriceLevel::query()->firstOrCreate(['organization_id' => $organizationId, 'currency_code' => $currencyCode, 'name' => 'wholesale']);

        CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->orderBy('sku')
            ->get(['id', 'sku', 'unit_group_id'])
            ->each(function (CatalogItem $item) use ($organizationId, $master, $retail, $wholesale): void {
                $unitId = $master->baseUnit($item->unit_group_id)->id;
                $retailAmount = 1_000 + (crc32($item->sku) % 500) * 100;

                foreach ([[$retail, $retailAmount], [$wholesale, intdiv($retailAmount * 9, 10)]] as [$level, $amount]) {
                    CatalogItemPrice::query()->firstOrCreate([
                        'organization_id' => $organizationId,
                        'catalog_item_id' => $item->id,
                        'price_level_id'  => $level->id,
                        'unit_id'         => $unitId,
                    ], ['amount' => $amount]);
                }
            });
    }
}

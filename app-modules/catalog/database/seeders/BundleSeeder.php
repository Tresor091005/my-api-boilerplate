<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Master\Models\Unit;

class BundleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizationId = currentOrganizationId();
        $bundleUnit = Unit::where('code', 'bundle')->first();

        // Get some product variants to add to bundles
        $variantSkus = [
            'IP15P-BLA-128',
            'USB-C-HUB-SIL',
            'MBP16-SG-16-512',
            'SGS24-WHI-256',
            'WDT-OAK',
        ];
        $variantsBySku = ProductVariant::query()
            ->where('catalog_product_variants.organization_id', $organizationId)
            ->whereHas('catalogItem', fn ($query) => $query->whereIn('sku', $variantSkus))
            ->with('catalogItem')
            ->get()
            ->keyBy(fn (ProductVariant $variant): string => $variant->catalogItem->sku);

        $variantIphoneBlack128 = $variantsBySku->get('IP15P-BLA-128');
        $variantUsbCHubSilver = $variantsBySku->get('USB-C-HUB-SIL');
        $variantMacbookSpaceGray16GB512GB = $variantsBySku->get('MBP16-SG-16-512');
        $variantSamsungWhite256GB = $variantsBySku->get('SGS24-WHI-256');
        $variantDiningTableOak = $variantsBySku->get('WDT-OAK');

        // Bundle 1: iPhone Starter Pack
        if ($variantIphoneBlack128 && $variantUsbCHubSilver && $bundleUnit) {
            /** @var Bundle $bundle */
            $bundle = Bundle::firstOrCreate(
                [
                    'handle'          => 'iphone-starter-pack',
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'name'            => 'iPhone Starter Pack',
                    'unit_code'       => $bundleUnit->code,
                    'is_active'       => true,
                ]
            );

            $bundle->items()->firstOrCreate(
                [
                    'item_id'         => $variantIphoneBlack128->id,
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'item_type'       => $variantIphoneBlack128->getMorphClass(),
                    'quantity'        => 1,
                ]
            );

            $bundle->items()->firstOrCreate(
                [
                    'item_id'         => $variantUsbCHubSilver->id,
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'item_type'       => $variantUsbCHubSilver->getMorphClass(),
                    'quantity'        => 1,
                ]
            );
        }

        // Bundle 2: Laptop & Hub Combo
        if ($variantMacbookSpaceGray16GB512GB && $variantUsbCHubSilver && $bundleUnit) {
            /** @var Bundle $bundle */
            $bundle = Bundle::firstOrCreate(
                [
                    'handle'          => 'laptop-hub-combo',
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'name'            => 'Laptop & Hub Combo',
                    'unit_code'       => $bundleUnit->code,
                    'is_active'       => true,
                ]
            );

            $bundle->items()->firstOrCreate(
                [
                    'item_id'         => $variantMacbookSpaceGray16GB512GB->id,
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'item_type'       => $variantMacbookSpaceGray16GB512GB->getMorphClass(),
                    'quantity'        => 1,
                ]
            );

            $bundle->items()->firstOrCreate(
                [
                    'item_id'         => $variantUsbCHubSilver->id,
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'item_type'       => $variantUsbCHubSilver->getMorphClass(),
                    'quantity'        => 2, // Varied quantity
                ]
            );
        }

        // Bundle 3: Smartphone Power Pack
        if ($variantSamsungWhite256GB && $variantUsbCHubSilver && $bundleUnit) {
            /** @var Bundle $bundle */
            $bundle = Bundle::firstOrCreate(
                [
                    'handle'          => 'smartphone-power-pack',
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'name'            => 'Smartphone Power Pack',
                    'unit_code'       => $bundleUnit->code,
                    'is_active'       => true,
                ]
            );

            $bundle->items()->firstOrCreate(
                [
                    'item_id'         => $variantSamsungWhite256GB->id,
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'item_type'       => $variantSamsungWhite256GB->getMorphClass(),
                    'quantity'        => 1,
                ]
            );

            $bundle->items()->firstOrCreate(
                [
                    'item_id'         => $variantUsbCHubSilver->id,
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'item_type'       => $variantUsbCHubSilver->getMorphClass(),
                    'quantity'        => 3, // Varied quantity
                ]
            );
        }

        // Bundle 4: Home Office Furniture
        if ($variantDiningTableOak && $variantUsbCHubSilver && $bundleUnit) {
            /** @var Bundle $bundle */
            $bundle = Bundle::firstOrCreate(
                [
                    'handle'          => 'home-office-furniture',
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'name'            => 'Home Office Furniture',
                    'unit_code'       => $bundleUnit->code,
                    'is_active'       => true,
                ]
            );

            $bundle->items()->firstOrCreate(
                [
                    'item_id'         => $variantDiningTableOak->id,
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'item_type'       => $variantDiningTableOak->getMorphClass(),
                    'quantity'        => 1,
                ]
            );

            $bundle->items()->firstOrCreate(
                [
                    'item_id'         => $variantUsbCHubSilver->id,
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'item_type'       => $variantUsbCHubSilver->getMorphClass(),
                    'quantity'        => 1,
                ]
            );
        }
    }
}

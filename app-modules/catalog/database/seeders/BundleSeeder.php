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
        $organizationId = getPermissionsTeamId();
        $bundleUnit = Unit::where('code', 'bundle')->first();

        // Get some product variants to add to bundles
        $variantIphoneBlack128 = ProductVariant::where('organization_id', $organizationId)->where('sku', 'IP15P-BLA-128')->first();
        $variantUsbCHubSilver = ProductVariant::where('organization_id', $organizationId)->where('sku', 'USB-C-HUB-SIL')->first();
        $variantMacbookSpaceGray16GB512GB = ProductVariant::where('organization_id', $organizationId)->where('sku', 'MBP16-SG-16-512')->first();
        $variantSamsungWhite256GB = ProductVariant::where('organization_id', $organizationId)->where('sku', 'SGS24-WHI-256')->first();
        $variantDiningTableOak = ProductVariant::where('organization_id', $organizationId)->where('sku', 'WDT-OAK')->first();

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

            $bundle->prices()->firstOrCreate(
                [
                    'currency_code'   => 'XOF',
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'amount'          => 770000,
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

            // Price calculation for bundle 2:
            // MacBook: 1,500,000 XOF, USB-C Hub: 25,000 XOF * 2 = 50,000 XOF. Total: 1,550,000 XOF
            // Discounted price
            $bundle->prices()->firstOrCreate(
                [
                    'currency_code'   => 'XOF',
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'amount'          => 1520000,
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

            // Price calculation for bundle 3:
            // Samsung S24: 650,000 XOF, USB-C Hub: 25,000 XOF * 3 = 75,000 XOF. Total: 725,000 XOF
            // Discounted price
            $bundle->prices()->firstOrCreate(
                [
                    'currency_code'   => 'XOF',
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'amount'          => 700000,
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

            // Price calculation for bundle 4:
            // Dining Table: 175,000 XOF, USB-C Hub: 25,000 XOF. Total: 200,000 XOF
            // Discounted price
            $bundle->prices()->firstOrCreate(
                [
                    'currency_code'   => 'XOF',
                    'organization_id' => $organizationId,
                ],
                [
                    'organization_id' => $organizationId,
                    'amount'          => 190000,
                ]
            );
        }
    }
}

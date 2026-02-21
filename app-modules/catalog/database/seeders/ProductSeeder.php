<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductOption;
use Lahatre\Catalog\Models\ProductOptionValue;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\Unit;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch common units needed for variants
        $kgUnit = Unit::where('code', 'kg')->first();
        $gUnit = Unit::where('code', 'g')->first();
        $mlUnit = Unit::where('code', 'ml')->first();
        $ctUnit = Unit::where('code', 'ct')->first();

        // Fetch product options and values
        $colorOption = ProductOption::where('code', 'color')->first();
        $storageOption = ProductOption::where('code', 'storage')->first();
        $ramOption = ProductOption::where('code', 'ram')->first();

        // Color Option Values
        $blackColor = ProductOptionValue::where('option_id', $colorOption->id)->where('code', 'black')->first();
        $whiteColor = ProductOptionValue::where('option_id', $colorOption->id)->where('code', 'white')->first();
        $silverColor = ProductOptionValue::where('option_id', $colorOption->id)->where('code', 'silver')->first();
        $spaceGrayColor = ProductOptionValue::where('option_id', $colorOption->id)->where('code', 'space-gray')->first();
        $blueColor = ProductOptionValue::where('option_id', $colorOption->id)->where('code', 'blue')->first();

        // Storage Option Values
        $storage128GB = ProductOptionValue::where('option_id', $storageOption->id)->where('code', '128gb')->first();
        $storage256GB = ProductOptionValue::where('option_id', $storageOption->id)->where('code', '256gb')->first();
        $storage512GB = ProductOptionValue::where('option_id', $storageOption->id)->where('code', '512gb')->first();
        $storage1TB = ProductOptionValue::where('option_id', $storageOption->id)->where('code', '1tb')->first();

        // RAM Option Values
        $ram8GB = ProductOptionValue::where('option_id', $ramOption->id)->where('code', '8gb')->first();
        $ram16GB = ProductOptionValue::where('option_id', $ramOption->id)->where('code', '16gb')->first();
        $ram32GB = ProductOptionValue::where('option_id', $ramOption->id)->where('code', '32gb')->first();

        $productsData = [
            [
                'handle'      => 'iphone-15-pro',
                'name'        => 'iPhone 15 Pro',
                'description' => 'The latest iPhone model with advanced features.',
                'is_active'   => true,
                'categories'  => ['smartphones'],
                'variants'    => [
                    [
                        'sku'           => 'IP15P-BLA-128',
                        'unit_code'     => $gUnit->code, // Example: for a phone, might be unit of weight
                        'min_quantity'  => 1,
                        'is_default'    => true,
                        'is_active'     => true,
                        'option_values' => [$blackColor, $storage128GB, $ram8GB],
                        'price'         => 750000,
                    ],
                    [
                        'sku'           => 'IP15P-SIL-256',
                        'unit_code'     => $gUnit->code,
                        'min_quantity'  => 1,
                        'is_default'    => false,
                        'is_active'     => true,
                        'option_values' => [$silverColor, $storage256GB, $ram16GB],
                        'price'         => 850000,
                    ],
                ],
            ],
            [
                'handle'      => 'samsung-galaxy-s24',
                'name'        => 'Samsung Galaxy S24',
                'description' => 'Flagship Android smartphone from Samsung.',
                'is_active'   => true,
                'categories'  => ['smartphones'],
                'variants'    => [
                    [
                        'sku'           => 'SGS24-WHI-256',
                        'unit_code'     => $gUnit->code,
                        'min_quantity'  => 1,
                        'is_default'    => true,
                        'is_active'     => true,
                        'option_values' => [$whiteColor, $storage256GB],
                        'price'         => 650000,
                    ],
                    [
                        'sku'           => 'SGS24-BLU-512',
                        'unit_code'     => $gUnit->code,
                        'min_quantity'  => 1,
                        'is_default'    => false,
                        'is_active'     => true,
                        'option_values' => [$blueColor, $storage512GB],
                        'price'         => 780000,
                    ],
                ],
            ],
            [
                'handle'      => 'macbook-pro-16-inch',
                'name'        => 'MacBook Pro 16-inch',
                'description' => 'Powerful laptop for professionals.',
                'is_active'   => true,
                'categories'  => ['laptops', 'business-laptops'],
                'variants'    => [
                    [
                        'sku'           => 'MBP16-SG-16-512',
                        'unit_code'     => $kgUnit->code,
                        'min_quantity'  => 1,
                        'is_default'    => true,
                        'is_active'     => true,
                        'option_values' => [$spaceGrayColor, $ram16GB, $storage512GB],
                        'price'         => 1500000,
                    ],
                    [
                        'sku'           => 'MBP16-SG-32-1TB',
                        'unit_code'     => $kgUnit->code,
                        'min_quantity'  => 1,
                        'is_default'    => false,
                        'is_active'     => true,
                        'option_values' => [$spaceGrayColor, $ram32GB, $storage1TB],
                        'price'         => 2100000,
                    ],
                ],
            ],
            [
                'handle'      => 'dell-alienware-m18',
                'name'        => 'Dell Alienware m18',
                'description' => 'High-performance gaming laptop.',
                'is_active'   => true,
                'categories'  => ['laptops', 'gaming-laptops'],
                'variants'    => [
                    [
                        'sku'           => 'AW-M18-BLA-32-1TB',
                        'unit_code'     => $kgUnit->code,
                        'min_quantity'  => 1,
                        'is_default'    => true,
                        'is_active'     => true,
                        'option_values' => [$blackColor, $ram32GB, $storage1TB],
                        'price'         => 2500000,
                    ],
                ],
            ],
            [
                'handle'      => 'usb-c-hub',
                'name'        => 'USB-C Hub',
                'description' => 'Multi-port adapter for USB-C devices.',
                'is_active'   => true,
                'categories'  => ['accessories'],
                'variants'    => [
                    [
                        'sku'           => 'USB-C-HUB-SIL',
                        'unit_code'     => $gUnit->code,
                        'min_quantity'  => 1,
                        'is_default'    => true,
                        'is_active'     => true,
                        'option_values' => [$silverColor],
                        'price'         => 25000,
                    ],
                ],
            ],
            [
                'handle'      => 'wooden-dining-table',
                'name'        => 'Wooden Dining Table',
                'description' => 'Elegant solid wood dining table.',
                'is_active'   => true,
                'categories'  => ['home-garden', 'furniture'],
                'variants'    => [
                    [
                        'sku'           => 'WDT-OAK',
                        'unit_code'     => $kgUnit->code,
                        'min_quantity'  => 1,
                        'is_default'    => true,
                        'is_active'     => true,
                        'option_values' => [], // No specific options for this example
                        'price'         => 175000,
                    ],
                ],
            ],
        ];

        foreach ($productsData as $productData) {
            $categoriesToAttach = $productData['categories'];
            $variantsData = $productData['variants'];
            unset($productData['categories'], $productData['variants']);

            $product = Product::firstOrCreate(
                ['handle' => $productData['handle']],
                $productData
            );

            // Attach categories
            $categoryIds = Category::whereIn('handle', $categoriesToAttach)->pluck('id');
            $product->categories()->sync($categoryIds);

            // Create and attach variants
            foreach ($variantsData as $variantData) {
                $optionValuesToAttach = $variantData['option_values'];
                $price = $variantData['price'];
                unset($variantData['option_values'], $variantData['price']);

                /** @var ProductVariant $variant */
                $variant = $product->variants()->firstOrCreate(
                    ['sku' => $variantData['sku']],
                    $variantData
                );

                $variant->prices()->create([
                    'currency_code' => 'XOF',
                    'amount'        => $price,
                ]);

                // Attach option values to the variant
                $attachments = [];
                foreach ($optionValuesToAttach as $optionValue) {
                    $attachments[$optionValue->id] = [
                        'product_id' => $product->id,
                        'option_id'  => $optionValue->option_id,
                    ];
                }

                if ($attachments !== []) {
                    $variant->optionValues()->sync($attachments);
                }
            }
        }
    }
}

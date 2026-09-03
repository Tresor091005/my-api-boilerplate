<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Data\ProductData;
use Lahatre\Catalog\Data\ProductVariantBatchData;
use Lahatre\Catalog\Data\ProductVariantUpdateData;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\ProductService;
use Lahatre\Catalog\Services\ProductVariantService;
use Lahatre\Master\Contracts\MasterInterface;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizationId = currentOrganizationId();
        $productService = app(ProductService::class);
        $productVariantService = app(ProductVariantService::class);
        $masterInterface = app(MasterInterface::class);

        $massGroupId = $masterInterface->unit('g')->group_id;

        // Fetch product options and values
        $colorOption = Option::where('organization_id', $organizationId)->where('name', 'color')->first();
        $storageOption = Option::where('organization_id', $organizationId)->where('name', 'storage')->first();
        $ramOption = Option::where('organization_id', $organizationId)->where('name', 'ram')->first();
        $optionNames = Option::query()
            ->where('organization_id', $organizationId)
            ->whereIn('name', ['color', 'storage', 'ram'])
            ->pluck('name', 'id');

        // Color Option Values
        $blackColor = OptionValue::where('organization_id', $organizationId)->where('option_id', $colorOption?->id)->where('value', 'black')->first();
        $whiteColor = OptionValue::where('organization_id', $organizationId)->where('option_id', $colorOption?->id)->where('value', 'white')->first();
        $silverColor = OptionValue::where('organization_id', $organizationId)->where('option_id', $colorOption?->id)->where('value', 'silver')->first();
        $spaceGrayColor = OptionValue::where('organization_id', $organizationId)->where('option_id', $colorOption?->id)->where('value', 'space-gray')->first();
        $blueColor = OptionValue::where('organization_id', $organizationId)->where('option_id', $colorOption?->id)->where('value', 'blue')->first();

        // Storage Option Values
        $storage128GB = OptionValue::where('organization_id', $organizationId)->where('option_id', $storageOption?->id)->where('value', '128gb')->first();
        $storage256GB = OptionValue::where('organization_id', $organizationId)->where('option_id', $storageOption?->id)->where('value', '256gb')->first();
        $storage512GB = OptionValue::where('organization_id', $organizationId)->where('option_id', $storageOption?->id)->where('value', '512gb')->first();
        $storage1TB = OptionValue::where('organization_id', $organizationId)->where('option_id', $storageOption?->id)->where('value', '1tb')->first();

        // RAM Option Values
        $ram8GB = OptionValue::where('organization_id', $organizationId)->where('option_id', $ramOption?->id)->where('value', '8gb')->first();
        $ram16GB = OptionValue::where('organization_id', $organizationId)->where('option_id', $ramOption?->id)->where('value', '16gb')->first();
        $ram32GB = OptionValue::where('organization_id', $organizationId)->where('option_id', $ramOption?->id)->where('value', '32gb')->first();

        $productsData = [
            [
                'handle'      => 'iphone-15-pro',
                'name'        => 'iPhone 15 Pro',
                'description' => 'The latest iPhone model with advanced features.',
                'categories'  => ['smartphones'],
                'variants'    => [
                    [
                        'sku'           => 'IP15P-BLA-128',
                        'unit_group_id' => $massGroupId,
                        'is_active'     => true,
                        'option_values' => [$blackColor, $storage128GB, $ram8GB],
                    ],
                    [
                        'sku'           => 'IP15P-SIL-256',
                        'unit_group_id' => $massGroupId,
                        'is_active'     => true,
                        'option_values' => [$silverColor, $storage256GB, $ram16GB],
                    ],
                ],
            ],
            [
                'handle'      => 'samsung-galaxy-s24',
                'name'        => 'Samsung Galaxy S24',
                'description' => 'Flagship Android smartphone from Samsung.',
                'categories'  => ['smartphones'],
                'variants'    => [
                    [
                        'sku'           => 'SGS24-WHI-256',
                        'unit_group_id' => $massGroupId,
                        'is_active'     => true,
                        'option_values' => [$whiteColor, $storage256GB],
                    ],
                    [
                        'sku'           => 'SGS24-BLU-512',
                        'unit_group_id' => $massGroupId,
                        'is_active'     => true,
                        'option_values' => [$blueColor, $storage512GB],
                    ],
                ],
            ],
            [
                'handle'      => 'macbook-pro-16-inch',
                'name'        => 'MacBook Pro 16-inch',
                'description' => 'Powerful laptop for professionals.',
                'categories'  => ['laptops', 'business-laptops'],
                'variants'    => [
                    [
                        'sku'           => 'MBP16-SG-16-512',
                        'unit_group_id' => $massGroupId,
                        'is_active'     => true,
                        'option_values' => [$spaceGrayColor, $ram16GB, $storage512GB],
                    ],
                    [
                        'sku'           => 'MBP16-SG-32-1TB',
                        'unit_group_id' => $massGroupId,
                        'is_active'     => true,
                        'option_values' => [$spaceGrayColor, $ram32GB, $storage1TB],
                    ],
                ],
            ],
            [
                'handle'      => 'dell-alienware-m18',
                'name'        => 'Dell Alienware m18',
                'description' => 'High-performance gaming laptop.',
                'categories'  => ['laptops', 'gaming-laptops'],
                'variants'    => [
                    [
                        'sku'           => 'AW-M18-BLA-32-1TB',
                        'unit_group_id' => $massGroupId,
                        'is_active'     => true,
                        'option_values' => [$blackColor, $ram32GB, $storage1TB],
                    ],
                ],
            ],
            [
                'handle'      => 'usb-c-hub',
                'name'        => 'USB-C Hub',
                'description' => 'Multi-port adapter for USB-C devices.',
                'categories'  => ['accessories'],
                'variants'    => [
                    [
                        'sku'           => 'USB-C-HUB-SIL',
                        'unit_group_id' => $massGroupId,
                        'is_active'     => true,
                        'option_values' => [$silverColor],
                    ],
                ],
            ],
            [
                'handle'      => 'wooden-dining-table',
                'name'        => 'Wooden Dining Table',
                'description' => 'Elegant solid wood dining table.',
                'categories'  => ['home-garden', 'furniture'],
                'variants'    => [
                    [
                        'sku'           => 'WDT-OAK',
                        'unit_group_id' => $massGroupId,
                        'is_active'     => true,
                        'option_values' => [],
                    ],
                ],
            ],
        ];

        foreach ($productsData as $productData) {
            $categoriesToAttach = $productData['categories'];
            $variantsData = $productData['variants'];
            unset($productData['categories'], $productData['variants']);

            $categoryIds = Category::where('organization_id', $organizationId)->whereIn('handle', $categoriesToAttach)->pluck('id');

            $variantsPayload = collect($variantsData)->map(
                fn (array $variantData): array => [
                    'sku'           => $variantData['sku'],
                    'unit_group_id' => $massGroupId,
                    'is_active'     => $variantData['is_active'],
                    'options'       => collect($variantData['option_values'])
                        ->filter()
                        ->map(fn (OptionValue $optionValue): array => [
                            'name'  => $optionNames->get($optionValue->option_id),
                            'value' => $optionValue->value,
                        ])->values()->all(),
                    'inventory' => [
                        'stock_tracking_enabled' => true,
                        'is_expirable'           => false,
                    ],
                ],
            )->all();

            $payload = [
                'name'        => $productData['name'],
                'description' => $productData['description'],
                'categories'  => $categoryIds->all(),
                'variants'    => $variantsPayload,
            ];

            $product = Product::query()
                ->where('organization_id', $organizationId)
                ->where('handle', $productData['handle'])
                ->first();

            if (!$product instanceof Product) {
                $productService->create(ProductData::fromArray($payload));

                continue;
            }

            $productService->update(
                $product,
                ProductData::fromArray($payload, missingFields: ['variants']),
            );

            foreach ($variantsPayload as $variantPayload) {
                /** @var ProductVariant|null $variant */
                $variant = $product->variants()
                    ->whereHas('catalogItem', fn ($query) => $query->where('sku', $variantPayload['sku']))
                    ->first();

                if ($variant === null) {
                    $productVariantService->create(
                        $product,
                        ProductVariantBatchData::fromArray(['variants' => [$variantPayload]]),
                    );

                    continue;
                }

                $productVariantService->update(
                    $product,
                    $variant,
                    ProductVariantUpdateData::fromArray($variantPayload),
                );
            }
        }
    }
}

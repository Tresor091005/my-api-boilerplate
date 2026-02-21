<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Models\ProductOption;
use Lahatre\Catalog\Models\ProductOptionValue;

class ProductOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $optionsData = [
            [
                'code'   => 'color',
                'name'   => 'Color',
                'values' => [
                    ['code' => 'black', 'value' => 'Black'],
                    ['code' => 'white', 'value' => 'White'],
                    ['code' => 'silver', 'value' => 'Silver'],
                    ['code' => 'space-gray', 'value' => 'Space Gray'],
                    ['code' => 'blue', 'value' => 'Blue'],
                    ['code' => 'red', 'value' => 'Red'],
                ],
            ],
            [
                'code'   => 'size',
                'name'   => 'Size',
                'values' => [
                    ['code' => 's', 'value' => 'Small'],
                    ['code' => 'm', 'value' => 'Medium'],
                    ['code' => 'l', 'value' => 'Large'],
                    ['code' => 'xl', 'value' => 'X-Large'],
                ],
            ],
            [
                'code'   => 'storage',
                'name'   => 'Storage',
                'values' => [
                    ['code' => '64gb', 'value' => '64 GB'],
                    ['code' => '128gb', 'value' => '128 GB'],
                    ['code' => '256gb', 'value' => '256 GB'],
                    ['code' => '512gb', 'value' => '512 GB'],
                    ['code' => '1tb', 'value' => '1 TB'],
                ],
            ],
            [
                'code'   => 'ram',
                'name'   => 'RAM',
                'values' => [
                    ['code' => '8gb', 'value' => '8 GB'],
                    ['code' => '16gb', 'value' => '16 GB'],
                    ['code' => '32gb', 'value' => '32 GB'],
                    ['code' => '64gb', 'value' => '64 GB'],
                ],
            ],
        ];

        foreach ($optionsData as $optionData) {
            $optionValues = $optionData['values'];
            unset($optionData['values']);

            $productOption = ProductOption::firstOrCreate(
                ['code' => $optionData['code']],
                $optionData
            );

            foreach ($optionValues as $optionValueData) {
                ProductOptionValue::firstOrCreate(
                    [
                        'option_id' => $productOption->id,
                        'code'      => $optionValueData['code'],
                    ],
                    $optionValueData
                );
            }
        }
    }
}

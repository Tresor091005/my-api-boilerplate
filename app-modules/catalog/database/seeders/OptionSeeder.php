<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;

class OptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $optionsData = [
            [
                'name'   => 'color',
                'values' => [
                    ['value' => 'black'],
                    ['value' => 'white'],
                    ['value' => 'silver'],
                    ['value' => 'space-gray'],
                    ['value' => 'blue'],
                    ['value' => 'red'],
                ],
            ],
            [
                'name'   => 'size',
                'values' => [
                    ['value' => 's'],
                    ['value' => 'm'],
                    ['value' => 'l'],
                    ['value' => 'xl'],
                ],
            ],
            [
                'name'   => 'storage',
                'values' => [
                    ['value' => '64gb'],
                    ['value' => '128gb'],
                    ['value' => '256gb'],
                    ['value' => '512gb'],
                    ['value' => '1tb'],
                ],
            ],
            [
                'name'   => 'ram',
                'values' => [
                    ['value' => '8gb'],
                    ['value' => '16gb'],
                    ['value' => '32gb'],
                    ['value' => '64gb'],
                ],
            ],
        ];

        foreach ($optionsData as $optionData) {
            $optionValues = $optionData['values'];
            unset($optionData['values']);

            $Option = Option::firstOrCreate(
                ['name' => $optionData['name']],
                $optionData
            );

            foreach ($optionValues as $optionValueData) {
                OptionValue::firstOrCreate(
                    [
                        'option_id' => $Option->id,
                        'value'     => $optionValueData['value'],
                    ],
                    $optionValueData
                );
            }
        }
    }
}

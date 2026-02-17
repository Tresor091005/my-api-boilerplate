<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Models\Currency;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'code'      => 'XOF',
                'name'      => 'West African CFA Franc',
                'symbol'    => 'FCFA',
                'precision' => 0,
            ],
            [
                'code'      => 'EUR',
                'name'      => 'Euro',
                'symbol'    => '€',
                'precision' => 2,
            ],
            [
                'code'      => 'USD',
                'name'      => 'US Dollar',
                'symbol'    => '$',
                'precision' => 2,
            ],
            [
                'code'      => 'CHF',
                'name'      => 'Swiss Franc',
                'symbol'    => 'CHF',
                'precision' => 2,
            ],
            [
                'code'      => 'GBP',
                'name'      => 'British Pound',
                'symbol'    => '£',
                'precision' => 2,
            ],
            [
                'code'      => 'JPY',
                'name'      => 'Japanese Yen',
                'symbol'    => '¥',
                'precision' => 0,
            ],
        ];

        foreach ($currencies as $currencyData) {
            Currency::firstOrCreate(
                ['code' => $currencyData['code']],
                $currencyData
            );
        }
    }
}

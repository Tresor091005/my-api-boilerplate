<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Seed basic currencies
        $currencies = [
            ['code' => 'XOF', 'name' => 'West African CFA Franc', 'symbol' => 'FCFA', 'precision' => 0],
            ['code' => 'EUR', 'name' => 'Euro',                   'symbol' => '€',    'precision' => 2],
            ['code' => 'USD', 'name' => 'US Dollar',              'symbol' => '$',    'precision' => 2],
            ['code' => 'CHF', 'name' => 'Swiss Franc',            'symbol' => 'CHF',  'precision' => 2],
            ['code' => 'GBP', 'name' => 'British Pound',          'symbol' => '£',    'precision' => 2],
            ['code' => 'JPY', 'name' => 'Japanese Yen',           'symbol' => '¥',    'precision' => 0],
        ];

        foreach ($currencies as $currency) {
            DB::table('catalog_currencies')->updateOrInsert(
                ['code' => $currency['code']],
                array_merge($currency, [
                    'id'         => (string) Str::uuid7(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Seed basic units
        $units = [
            // Mass Group
            ['code' => 'mg', 'name' => 'Milligram', 'ratio' => 1,          'symbol' => 'mg', 'unit_group' => 'mass', 'is_builtin' => true, 'is_active' => true],
            ['code' => 'g',  'name' => 'Gram',      'ratio' => 1_000,      'symbol' => 'g',  'unit_group' => 'mass', 'is_builtin' => true, 'is_active' => true],
            ['code' => 'kg', 'name' => 'Kilogram',  'ratio' => 1_000_000,  'symbol' => 'Kg', 'unit_group' => 'mass', 'is_builtin' => true, 'is_active' => true],
            ['code' => 't',  'name' => 'Tonne',     'ratio' => 1_000_000_000, 'symbol' => 'T',  'unit_group' => 'mass', 'is_builtin' => true, 'is_active' => true],
            // Length Group
            ['code' => 'mm', 'name' => 'Millimeter', 'ratio' => 1,       'symbol' => 'mm', 'unit_group' => 'length', 'is_builtin' => true, 'is_active' => true],
            ['code' => 'cm', 'name' => 'Centimeter', 'ratio' => 10,      'symbol' => 'cm', 'unit_group' => 'length', 'is_builtin' => true, 'is_active' => true],
            ['code' => 'dm', 'name' => 'Decimeter',  'ratio' => 100,     'symbol' => 'dm', 'unit_group' => 'length', 'is_builtin' => true, 'is_active' => true],
            ['code' => 'm',  'name' => 'Meter',      'ratio' => 1_000,    'symbol' => 'm',  'unit_group' => 'length', 'is_builtin' => true, 'is_active' => true],
            ['code' => 'km', 'name' => 'Kilometer',  'ratio' => 1_000_000, 'symbol' => 'Km', 'unit_group' => 'length', 'is_builtin' => true, 'is_active' => true],
            // Volume Group
            ['code' => 'ml', 'name' => 'Milliliter', 'ratio' => 1,    'symbol' => 'ml', 'unit_group' => 'volume', 'is_builtin' => true, 'is_active' => true],
            ['code' => 'cl', 'name' => 'Centiliter', 'ratio' => 10,   'symbol' => 'cl', 'unit_group' => 'volume', 'is_builtin' => true, 'is_active' => true],
            ['code' => 'dl', 'name' => 'Deciliter',  'ratio' => 100,  'symbol' => 'dl', 'unit_group' => 'volume', 'is_builtin' => true, 'is_active' => true],
            ['code' => 'l',  'name' => 'Liter',      'ratio' => 1_000, 'symbol' => 'L',  'unit_group' => 'volume', 'is_builtin' => true, 'is_active' => true],
            // Others
            ['code' => 'ct',     'name' => 'Carton',           'ratio' => 1,  'symbol' => null, 'unit_group' => 'packaging', 'is_builtin' => true, 'is_active' => true],
            ['code' => 'b',      'name' => 'Bottle',           'ratio' => 1,  'symbol' => 'b',  'unit_group' => 'bottle',    'is_builtin' => true, 'is_active' => true],
            ['code' => 'b-6',    'name' => 'Bottle (6 pack)',  'ratio' => 6,  'symbol' => 'b6', 'unit_group' => 'bottle',    'is_builtin' => true, 'is_active' => true],
            ['code' => 'b-12',   'name' => 'Bottle (12 pack)', 'ratio' => 12, 'symbol' => 'b12', 'unit_group' => 'bottle',   'is_builtin' => true, 'is_active' => true],
            ['code' => 'b-24',   'name' => 'Bottle (24 pack)', 'ratio' => 24, 'symbol' => 'b24', 'unit_group' => 'bottle',   'is_builtin' => true, 'is_active' => true],
            ['code' => 'bundle', 'name' => 'Bundle',           'ratio' => 1,  'symbol' => null, 'unit_group' => 'bundle',   'is_builtin' => true, 'is_active' => true],
        ];

        foreach ($units as $unit) {
            DB::table('catalog_units')->updateOrInsert(
                ['code' => $unit['code'], 'unit_group' => $unit['unit_group']],
                array_merge($unit, [
                    'id'         => (string) Str::uuid7(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

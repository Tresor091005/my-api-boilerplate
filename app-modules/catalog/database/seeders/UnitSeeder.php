<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Models\Unit;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            // Mass Group
            ['code' => 'mg', 'name' => 'Milligram', 'ratio' => 1,          'symbol' => 'mg', 'unit_group' => 'mass'],
            ['code' => 'g',  'name' => 'Gram',      'ratio' => 1_000,      'symbol' => 'g',  'unit_group' => 'mass'],
            ['code' => 'kg', 'name' => 'Kilogram',  'ratio' => 1_000_000,  'symbol' => 'Kg', 'unit_group' => 'mass'],
            ['code' => 't',  'name' => 'Tonne',     'ratio' => 1_000_000_000, 'symbol' => 'T',  'unit_group' => 'mass'],

            // Length Group
            ['code' => 'mm', 'name' => 'Millimeter', 'ratio' => 1,       'symbol' => 'mm', 'unit_group' => 'length'],
            ['code' => 'cm', 'name' => 'Centimeter', 'ratio' => 10,      'symbol' => 'cm', 'unit_group' => 'length'],
            ['code' => 'dm', 'name' => 'Decimeter',  'ratio' => 100,     'symbol' => 'dm', 'unit_group' => 'length'],
            ['code' => 'm',  'name' => 'Meter',      'ratio' => 1_000,    'symbol' => 'm',  'unit_group' => 'length'],
            ['code' => 'km', 'name' => 'Kilometer',  'ratio' => 1_000_000, 'symbol' => 'Km', 'unit_group' => 'length'],

            // Volume Group
            ['code' => 'ml', 'name' => 'Milliliter', 'ratio' => 1,    'symbol' => 'ml', 'unit_group' => 'volume'],
            ['code' => 'cl', 'name' => 'Centiliter', 'ratio' => 10,   'symbol' => 'cl', 'unit_group' => 'volume'],
            ['code' => 'dl', 'name' => 'Deciliter',  'ratio' => 100,  'symbol' => 'dl', 'unit_group' => 'volume'],
            ['code' => 'l',  'name' => 'Liter',      'ratio' => 1_000, 'symbol' => 'L',  'unit_group' => 'volume'],

            // Carton Group
            ['code' => 'ct', 'name' => 'Carton', 'ratio' => 1, 'symbol' => null, 'unit_group' => 'packaging'],

            // Bottle Group
            ['code' => 'b', 'name' => 'Bottle', 'ratio' => 1,  'symbol' => 'b', 'unit_group' => 'bottle'],
            ['code' => 'b-6', 'name' => 'Bottle (6 pack)', 'ratio' => 6,  'symbol' => 'b6', 'unit_group' => 'bottle'],
            ['code' => 'b-12', 'name' => 'Bottle (12 pack)', 'ratio' => 12, 'symbol' => 'b12', 'unit_group' => 'bottle'],
            ['code' => 'b-24', 'name' => 'Bottle (24 pack)', 'ratio' => 24, 'symbol' => 'b24', 'unit_group' => 'bottle'],
        ];

        foreach ($units as $unitData) {
            Unit::firstOrCreate(
                ['code' => $unitData['code'], 'unit_group' => $unitData['unit_group']],
                $unitData
            );
        }
    }
}

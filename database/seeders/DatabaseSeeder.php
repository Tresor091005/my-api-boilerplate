<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company\Company;
use App\Models\User\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Lahatre\Catalog\Database\Seeders\BundleSeeder;
use Lahatre\Catalog\Database\Seeders\CategorySeeder;
use Lahatre\Catalog\Database\Seeders\CurrencySeeder;
use Lahatre\Catalog\Database\Seeders\ProductOptionSeeder;
use Lahatre\Catalog\Database\Seeders\ProductSeeder;
use Lahatre\Catalog\Database\Seeders\ProductTagSeeder;
use Lahatre\Catalog\Database\Seeders\UnitSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            UnitSeeder::class,
            CategorySeeder::class,
            ProductOptionSeeder::class,
            ProductSeeder::class,
            ProductTagSeeder::class,
            BundleSeeder::class,
        ]);

        User::firstOrCreate(
            ['email' => 'admin@lahatre.com'],
            [
                'first_name' => 'Test',
                'last_name'  => 'User',
                'password'   => 'password',
            ]
        );

        $company = Company::firstOrCreate(
            ['name' => 'kouri']
        );

        $company->members()->firstOrCreate(
            ['email' => 'admin2@lahatre.com'],
            [
                'first_name' => 'Xane',
                'last_name'  => 'Mikane',
                'password'   => 'password',
            ]
        );
    }
}

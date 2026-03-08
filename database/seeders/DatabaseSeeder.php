<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company\Company;
use App\Models\User\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Lahatre\Catalog\Database\Seeders\BundleSeeder;
use Lahatre\Catalog\Database\Seeders\CategorySeeder;
use Lahatre\Catalog\Database\Seeders\OptionSeeder;
use Lahatre\Catalog\Database\Seeders\ProductSeeder;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            OptionSeeder::class,
            ProductSeeder::class,
            BundleSeeder::class,
        ]);

        $user = User::firstOrCreate(
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

        Artisan::call('permissions:discover');

        setPermissionsTeamId(getDefaultTeamId());

        $user->assignRole(Role::whereName(SysRole::Administrator->value)->first());
    }
}

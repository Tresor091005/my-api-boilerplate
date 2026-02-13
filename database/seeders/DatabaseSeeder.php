<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company\Company;
use App\Models\User\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'admin@lahatre.com',
            'password'   => 'password',
        ]);

        $c = Company::create([
            'name' => 'kouri',
        ]);

        $c->members()->create([
            'first_name' => 'Xane',
            'last_name'  => 'Mikane',
            'email'      => 'admin2@lahatre.com',
            'password'   => 'password',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Iam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Iam\Models\Permission;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => fake()->unique()->word(),
            'title'       => fake()->words(2, true),
            'description' => fake()->sentence(),
            'guard_name'  => 'api',
        ];
    }
}

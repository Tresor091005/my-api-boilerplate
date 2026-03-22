<?php

declare(strict_types=1);

namespace Lahatre\Iam\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Iam\Models\Role;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => fake()->unique()->word(),
            'is_builtin'  => false,
            'description' => fake()->sentence(),
            'guard_name'  => 'api',
        ];
    }
}

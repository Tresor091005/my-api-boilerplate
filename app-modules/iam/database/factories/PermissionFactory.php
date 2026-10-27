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
            'name'       => fake()->unique()->word().'.list',
            'guard_name' => 'sanctum',
        ];
    }
}

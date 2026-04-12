<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Product;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => getPermissionsTeamId() ?? Organization::factory(),
            'handle'          => fake()->unique()->slug(),
            'name'            => fake()->words(3, true),
            'description'     => fake()->paragraph(),
            'is_active'       => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'handle'      => fake()->unique()->slug(),
            'name'        => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'is_active'   => true,
        ];
    }
}

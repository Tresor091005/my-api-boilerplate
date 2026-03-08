<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Category;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->unique()->word(),
            'handle'    => $this->faker->unique()->slug(),
            'parent_id' => null,
            'is_active' => true,
        ];
    }
}

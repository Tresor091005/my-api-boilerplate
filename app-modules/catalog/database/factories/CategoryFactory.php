<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Category;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => getPermissionsTeamId() ?? Organization::factory(),
            'name'            => $this->faker->unique()->word(),
            'handle'          => $this->faker->unique()->slug(),
            'parent_id'       => null,
            'is_active'       => true,
        ];
    }
}

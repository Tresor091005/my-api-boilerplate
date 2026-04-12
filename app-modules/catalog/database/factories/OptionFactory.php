<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Option;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<Option>
 */
class OptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => getPermissionsTeamId() ?? Organization::factory(),
            'name'            => fake()->unique()->word(),
        ];
    }
}

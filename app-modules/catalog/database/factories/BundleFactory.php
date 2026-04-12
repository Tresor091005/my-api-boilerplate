<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Master\Models\Unit;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<Bundle>
 */
class BundleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => getPermissionsTeamId() ?? Organization::factory(),
            'handle'          => fake()->unique()->slug(),
            'name'            => fake()->words(3, true),
            'unit_code'       => Unit::factory(),
            'step'            => 1,
            'is_active'       => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<OptionValue>
 */
class OptionValueFactory extends Factory
{
    public function definition(): array
    {
        $organizationId = getPermissionsTeamId() ?? Organization::factory();

        return [
            'organization_id' => $organizationId,
            'option_id'       => Option::factory(['organization_id' => $organizationId]),
            'value'           => fake()->word(),
        ];
    }
}

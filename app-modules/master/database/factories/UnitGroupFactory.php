<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Master\Models\UnitGroup;

/**
 * @extends Factory<UnitGroup>
 */
class UnitGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'       => $this->faker->unique()->word(),
            'is_builtin' => false,
        ];
    }
}

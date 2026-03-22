<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'code'     => $this->faker->unique()->lexify('???'),
            'name'     => $name,
            'symbol'   => Str::upper(Str::limit($name, 2, '')),
            'ratio'    => $this->faker->numberBetween(1, 1000),
            'group_id' => UnitGroup::factory()->create()->id,
        ];
    }
}

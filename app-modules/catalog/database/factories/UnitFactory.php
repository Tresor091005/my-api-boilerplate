<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\Unit;
use Lahatre\Catalog\Models\UnitGroup;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'code'     => Str::slug($name),
            'name'     => $name,
            'symbol'   => Str::upper(Str::limit($name, 2, '')),
            'ratio'    => $this->faker->numberBetween(1, 1000),
            'group_id' => UnitGroup::factory(),
        ];
    }
}

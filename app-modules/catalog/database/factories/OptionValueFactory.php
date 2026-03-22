<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;

/**
 * @extends Factory<OptionValue>
 */
class OptionValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'option_id' => Option::factory(),
            'value'     => fake()->word(),
        ];
    }
}

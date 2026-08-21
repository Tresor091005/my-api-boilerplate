<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Master\Models\Label;
use Lahatre\Master\Models\Labelable;

/**
 * @extends Factory<Labelable>
 */
class LabelableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label_id'       => Label::factory(),
            'labelable_type' => 'master_label',
            'labelable_id'   => fake()->uuid(),
        ];
    }
}

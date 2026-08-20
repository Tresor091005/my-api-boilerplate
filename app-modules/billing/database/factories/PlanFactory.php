<?php

declare(strict_types=1);

namespace Lahatre\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Billing\Models\Plan;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code'      => fake()->unique()->slug(2),
            'name'      => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Models\Feature;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key'          => fake()->unique()->slug(2),
            'name'         => fake()->words(2, true),
            'type'         => FeatureType::Boolean,
            'resolver_key' => null,
            'is_active'    => true,
        ];
    }
}

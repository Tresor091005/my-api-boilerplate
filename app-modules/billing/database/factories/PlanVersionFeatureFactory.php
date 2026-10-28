<?php

declare(strict_types=1);

namespace Lahatre\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\PlanVersionFeature;

/**
 * @extends Factory<PlanVersionFeature>
 */
class PlanVersionFeatureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_version_id' => PlanVersion::factory(),
            'feature_key'     => fake()->unique()->slug(2),
            'feature_type'    => FeatureType::Boolean,
            'allowance'       => null,
        ];
    }
}

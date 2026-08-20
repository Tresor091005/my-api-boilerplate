<?php

declare(strict_types=1);

namespace Lahatre\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Billing\Enums\PlanVersionStatus;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Models\PlanVersion;

/**
 * @extends Factory<PlanVersion>
 */
class PlanVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id'          => Plan::factory(),
            'version'          => 1,
            'status'           => PlanVersionStatus::Draft,
            'price'            => 10,
            'currency_code'    => 'USD',
            'billing_interval' => 1,
            'published_at'     => null,
        ];
    }
}

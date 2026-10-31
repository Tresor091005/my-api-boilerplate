<?php

declare(strict_types=1);

namespace Lahatre\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\Trial;

/**
 * @extends Factory<Trial>
 */
class TrialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => currentOrganizationId(...),
            'plan_version_id' => PlanVersion::factory(),
            'starts_at'       => now()->startOfSecond(),
            'ends_at'         => now()->startOfSecond()->addMonthNoOverflow(),
        ];
    }
}

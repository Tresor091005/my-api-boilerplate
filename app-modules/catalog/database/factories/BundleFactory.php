<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<Bundle>
 */
class BundleFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();

        return [
            'organization_id' => $organizationId,
            'handle'          => fake()->unique()->slug(),
            'name'            => fake()->words(3, true),
            'unit_code'       => Unit::factory(),
            'step'            => 1,
            'is_active'       => true,
        ];
    }
}

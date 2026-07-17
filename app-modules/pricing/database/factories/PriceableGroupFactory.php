<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Pricing\Models\PriceableGroup;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<PriceableGroup>
 */
class PriceableGroupFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();

        return [
            'organization_id' => $organizationId,
            'name'            => fake()->unique()->words(2, true),
            'description'     => fake()->sentence(),
            'is_active'       => true,
            'metadata'        => null,
        ];
    }
}

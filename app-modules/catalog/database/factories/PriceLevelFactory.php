<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<PriceLevel>
 */
class PriceLevelFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'name'            => fake()->unique()->numerify('level ####'),
            'currency_code'   => 'XOF',
        ];
    }
}

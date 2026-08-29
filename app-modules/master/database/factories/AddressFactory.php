<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Master\Models\Address;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'line'            => fake()->streetAddress(),
            'city'            => fake()->city(),
            'country'         => fake()->country(),
            'is_primary'      => false,
        ];
    }
}

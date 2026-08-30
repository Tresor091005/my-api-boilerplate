<?php

declare(strict_types=1);

namespace Lahatre\Customer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Customer\Enums\CustomerType;
use Lahatre\Customer\Models\Customer;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id'       => $this->resolveOrganizationId(),
            'type'                  => CustomerType::Individual,
            'name'                  => fake()->name(),
            'identification_number' => null,
            'is_active'             => true,
        ];
    }
}

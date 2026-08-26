<?php

declare(strict_types=1);

namespace Lahatre\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'                     => fake()->name(),
            'functional_currency_code' => 'XOF',
        ];
    }
}

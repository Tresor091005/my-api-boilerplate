<?php

declare(strict_types=1);

namespace Lahatre\Tax\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Organization\Models\Organization;
use Lahatre\Tax\Models\TaxProfile;

/**
 * @extends Factory<TaxProfile>
 */
class TaxProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code'            => Str::toUpper(fake()->unique()->bothify('PROFILE-####??')),
            'name'            => fake()->words(2, true),
            'is_active'       => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

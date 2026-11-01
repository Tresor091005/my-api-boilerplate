<?php

declare(strict_types=1);

namespace Lahatre\Tax\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Organization\Models\Organization;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Models\TaxProfileLine;

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

    /** Every profile gets one standard line on a new 18% tax. */
    public function configure(): static
    {
        return $this->afterCreating(function (TaxProfile $profile): void {
            TaxProfileLine::factory()->create([
                'organization_id' => $profile->organization_id,
                'tax_profile_id'  => $profile->id,
            ]);
        });
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

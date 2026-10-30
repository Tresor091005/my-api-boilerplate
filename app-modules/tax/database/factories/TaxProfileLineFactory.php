<?php

declare(strict_types=1);

namespace Lahatre\Tax\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Tax\Enums\TaxTreatment;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Models\TaxProfileLine;

/**
 * @extends Factory<TaxProfileLine>
 */
class TaxProfileLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tax_profile_id'  => TaxProfile::factory(),
            'organization_id' => fn (array $attributes): string => TaxProfile::query()->findOrFail($attributes['tax_profile_id'])->organization_id,
            'tax_id'          => fn (array $attributes): string => Tax::factory()->withValue('18')->create(['organization_id' => $attributes['organization_id']])->id,
            'treatment'       => TaxTreatment::Standard,
        ];
    }
}

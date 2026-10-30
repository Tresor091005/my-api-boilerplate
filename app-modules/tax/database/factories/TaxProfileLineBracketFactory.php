<?php

declare(strict_types=1);

namespace Lahatre\Tax\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Tax\Enums\TaxTreatment;
use Lahatre\Tax\Models\TaxBracket;
use Lahatre\Tax\Models\TaxProfileLine;
use Lahatre\Tax\Models\TaxProfileLineBracket;

/**
 * @extends Factory<TaxProfileLineBracket>
 */
class TaxProfileLineBracketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tax_profile_line_id' => TaxProfileLine::factory()->state(['treatment' => TaxTreatment::Custom]),
            'organization_id'     => fn (array $attributes): string => TaxProfileLine::query()->findOrFail($attributes['tax_profile_line_id'])->organization_id,
            'tax_bracket_id'      => fn (array $attributes): string => TaxBracket::query()
                ->where('organization_id', $attributes['organization_id'])
                ->where('tax_id', TaxProfileLine::query()->findOrFail($attributes['tax_profile_line_id'])->tax_id)
                ->orderBy('base_min')
                ->firstOrFail()
                ->id,
            'value' => '5',
        ];
    }
}

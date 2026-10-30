<?php

declare(strict_types=1);

namespace Lahatre\Tax\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxBracket;

/**
 * @extends Factory<TaxBracket>
 */
class TaxBracketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tax_id'          => Tax::factory(),
            'organization_id' => fn (array $attributes): string => Tax::query()->findOrFail($attributes['tax_id'])->organization_id,
            'base_min'        => '0',
            'base_max'        => null,
            'value'           => '18',
            'tax_amount_min'  => null,
            'tax_amount_max'  => null,
        ];
    }
}

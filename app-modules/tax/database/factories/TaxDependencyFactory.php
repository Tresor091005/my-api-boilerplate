<?php

declare(strict_types=1);

namespace Lahatre\Tax\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Tax\Enums\TaxDependencyOperator;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxDependency;

/**
 * @extends Factory<TaxDependency>
 */
class TaxDependencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tax_id'            => Tax::factory(),
            'organization_id'   => fn (array $attributes): string => Tax::query()->findOrFail($attributes['tax_id'])->organization_id,
            'depends_on_tax_id' => fn (array $attributes): string => Tax::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'operator'          => TaxDependencyOperator::Add,
        ];
    }
}

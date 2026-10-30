<?php

declare(strict_types=1);

namespace Lahatre\Tax\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Master\Models\Unit;
use Lahatre\Organization\Models\Organization;
use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxBracketMode;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Enums\TaxKind;
use Lahatre\Tax\Enums\TaxRoundingMode;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxBracket;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id'  => Organization::factory(),
            'code'             => Str::toUpper(fake()->unique()->bothify('TAX-####??')),
            'name'             => fake()->words(2, true),
            'kind'             => TaxKind::Other,
            'calculation_type' => TaxCalculationType::Percentage,
            'fixed_mode'       => null,
            'tax_unit_id'      => null,
            'base_type'        => TaxBaseType::AmountWithDependencies,
            'is_progressive'   => false,
            'bracket_mode'     => null,
            'rounding_mode'    => TaxRoundingMode::HalfUp,
            'is_active'        => true,
        ];
    }

    public function vat(): static
    {
        return $this->state(['kind' => TaxKind::Vat]);
    }

    public function fixedPerLine(): static
    {
        return $this->state([
            'calculation_type' => TaxCalculationType::Fixed,
            'fixed_mode'       => TaxFixedMode::PerLine,
        ]);
    }

    public function fixedPerTaxUnit(Unit $unit): static
    {
        return $this->state([
            'calculation_type' => TaxCalculationType::Fixed,
            'fixed_mode'       => TaxFixedMode::PerTaxUnit,
            'tax_unit_id'      => $unit->id,
        ]);
    }

    public function progressive(?TaxBracketMode $bracketMode = TaxBracketMode::WholeAmount): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_progressive' => true,
            'bracket_mode'   => $attributes['calculation_type'] === TaxCalculationType::Fixed ? null : $bracketMode,
        ]);
    }

    public function dependenciesOnly(): static
    {
        return $this->state(['base_type' => TaxBaseType::DependenciesOnly]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** Single open bracket [0, ∞[ carrying the given value. */
    public function withValue(string $value): static
    {
        return $this->withBrackets([['base_min' => '0', 'base_max' => null, 'value' => $value]]);
    }

    /**
     * @param  list<array{base_min: string, base_max: string|null, value: string, tax_amount_min?: string|null, tax_amount_max?: string|null}>  $brackets
     */
    public function withBrackets(array $brackets): static
    {
        return $this->afterCreating(function (Tax $tax) use ($brackets): void {
            foreach ($brackets as $bracket) {
                TaxBracket::factory()->create([
                    'organization_id' => $tax->organization_id,
                    'tax_id'          => $tax->id,
                    'base_min'        => $bracket['base_min'],
                    'base_max'        => $bracket['base_max'],
                    'value'           => $bracket['value'],
                    'tax_amount_min'  => $bracket['tax_amount_min'] ?? null,
                    'tax_amount_max'  => $bracket['tax_amount_max'] ?? null,
                ]);
            }
        });
    }
}

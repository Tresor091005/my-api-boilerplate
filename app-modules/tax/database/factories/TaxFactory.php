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

    /**
     * Every tax gets a single open bracket [0, ∞[ at 18 unless withBrackets()
     * replaces it, and keeps a bracket mode only when progressive and percentage.
     */
    public function configure(): static
    {
        return $this
            ->afterMaking(function (Tax $tax): void {
                $usesBracketMode = $tax->is_progressive && $tax->calculation_type === TaxCalculationType::Percentage;
                $tax->bracket_mode = $usesBracketMode ? ($tax->bracket_mode ?? TaxBracketMode::WholeAmount) : null;
            })
            ->afterCreating(function (Tax $tax): void {
                TaxBracket::factory()->create([
                    'organization_id' => $tax->organization_id,
                    'tax_id'          => $tax->id,
                ]);
            });
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

    /** Two contiguous brackets: 0 below 10000, 18 from 10000. */
    public function progressive(TaxBracketMode $bracketMode = TaxBracketMode::WholeAmount): static
    {
        return $this
            ->state(['is_progressive' => true, 'bracket_mode' => $bracketMode])
            ->withBrackets([
                ['base_min' => '0', 'base_max' => '10000', 'value' => '0'],
                ['base_min' => '10000', 'base_max' => null, 'value' => '18'],
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
     * Replaces the brackets created so far; an empty list leaves the tax without any.
     *
     * @param  list<array{base_min: string, base_max: string|null, value: string, tax_amount_min?: string|null, tax_amount_max?: string|null}>  $brackets
     */
    public function withBrackets(array $brackets): static
    {
        return $this->afterCreating(function (Tax $tax) use ($brackets): void {
            TaxBracket::query()->where('organization_id', $tax->organization_id)->where('tax_id', $tax->id)->delete();

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

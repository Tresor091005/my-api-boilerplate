<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

use Illuminate\Support\Collection;
use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxBracketMode;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxDependencyOperator;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\ViewData\TaxBracketResultViewData;
use Lahatre\Tax\ViewData\TaxDependencyResultViewData;
use Lahatre\Tax\ViewData\TaxLineResultViewData;

/**
 * Pure calculation over a compiled profile, in minor units with BCMath.
 * Per bracket: raw amount, then `tax_amount_min`, then `tax_amount_max`, then
 * rounding to a whole minor unit with the tax's rounding mode. A tax amount is
 * the sum of its rounded brackets.
 */
final readonly class TaxCalculator
{
    private const int SCALE = 10;

    /**
     * @param  array<string, string>  $taxQuantities  line quantity in each per-unit tax's unit, keyed by tax id
     *
     * @throws TaxException
     *
     * @return Collection<int, TaxLineResultViewData> in calculation order
     */
    public function calculate(CompiledTaxProfile $profile, string $baseAmount, array $taxQuantities): Collection
    {
        $amounts = [];
        $codes = [];
        $results = collect();

        foreach ($profile->taxes as $tax) {
            $dependencies = collect($tax->dependencies)->map(fn (CompiledDependency $dependency): TaxDependencyResultViewData => new TaxDependencyResultViewData(
                taxId: $dependency->taxId,
                code: $codes[$dependency->taxId],
                operator: $dependency->operator,
                taxAmount: $amounts[$dependency->taxId],
            ));
            $dependencyAmount = $dependencies->reduce(
                fn (string $sum, TaxDependencyResultViewData $dependency): string => $dependency->operator === TaxDependencyOperator::Add
                    ? bcadd($sum, $dependency->taxAmount, 0)
                    : bcsub($sum, $dependency->taxAmount, 0),
                '0',
            );
            $inputBaseAmount = $tax->baseType === TaxBaseType::AmountWithDependencies ? $baseAmount : '0';
            $taxableBaseAmount = bcadd($inputBaseAmount, $dependencyAmount, 0);
            $taxQuantity = $tax->isChargedPerTaxUnit() ? ($taxQuantities[$tax->id] ?? null) : null;
            $brackets = $tax->isApplicable()
                ? $this->calculateBrackets($tax, $taxableBaseAmount, (string) $taxQuantity)
                : collect();
            $taxAmount = $brackets->reduce(
                fn (string $sum, TaxBracketResultViewData $bracket): string => bcadd($sum, $bracket->taxAmount, 0),
                '0',
            );

            $amounts[$tax->id] = $taxAmount;
            $codes[$tax->id] = $tax->code;
            $results->push(new TaxLineResultViewData(
                taxId: $tax->id,
                code: $tax->code,
                name: $tax->name,
                kind: $tax->kind,
                treatment: $tax->treatment,
                calculationType: $tax->calculationType,
                fixedMode: $tax->fixedMode,
                isProgressive: $tax->isProgressive,
                bracketMode: $tax->bracketMode,
                baseType: $tax->baseType,
                roundingMode: $tax->roundingMode,
                inputBaseAmount: $inputBaseAmount,
                dependencyAmount: $dependencyAmount,
                taxableBaseAmount: $taxableBaseAmount,
                taxQuantity: $taxQuantity,
                taxUnitCode: $tax->isChargedPerTaxUnit() ? $tax->taxUnitCode : null,
                brackets: $brackets,
                dependencies: $dependencies->values(),
                taxAmount: $taxAmount,
            ));
        }

        return $results;
    }

    /**
     * @throws TaxException
     *
     * @return Collection<int, TaxBracketResultViewData>
     */
    private function calculateBrackets(CompiledTax $tax, string $taxableBaseAmount, string $taxQuantity): Collection
    {
        if (bccomp($taxableBaseAmount, '0', 0) < 0) {
            throw TaxException::negativeTaxableBase($tax->code);
        }

        if ($tax->calculationType === TaxCalculationType::Percentage && $tax->bracketMode === TaxBracketMode::Marginal) {
            return collect($tax->brackets)
                ->filter(fn (CompiledBracket $bracket): bool => bccomp($taxableBaseAmount, $bracket->baseMin, 0) > 0)
                ->map(fn (CompiledBracket $bracket): TaxBracketResultViewData => $this->bracketResult(
                    $tax,
                    $bracket,
                    $this->portionWithin($bracket, $taxableBaseAmount),
                    $taxQuantity,
                ))
                ->values();
        }

        $bracket = collect($tax->brackets)->first(fn (CompiledBracket $bracket): bool => $bracket->contains($taxableBaseAmount));

        return collect([$this->bracketResult($tax, $bracket, $taxableBaseAmount, $taxQuantity)]);
    }

    private function portionWithin(CompiledBracket $bracket, string $taxableBaseAmount): string
    {
        $upper = $bracket->baseMax === null || bccomp($taxableBaseAmount, $bracket->baseMax, 0) < 0
            ? $taxableBaseAmount
            : $bracket->baseMax;

        return bcsub($upper, $bracket->baseMin, 0);
    }

    private function bracketResult(CompiledTax $tax, CompiledBracket $bracket, string $taxablePortion, string $taxQuantity): TaxBracketResultViewData
    {
        $rawAmount = match (true) {
            $tax->calculationType === TaxCalculationType::Percentage => bcdiv(bcmul($taxablePortion, $bracket->value, self::SCALE), '100', self::SCALE),
            $tax->fixedMode === TaxFixedMode::PerTaxUnit             => bcmul($bracket->value, $taxQuantity, self::SCALE),
            default                                                  => $bracket->value,
        };

        if ($bracket->taxAmountMin !== null && bccomp($rawAmount, $bracket->taxAmountMin, self::SCALE) < 0) {
            $rawAmount = $bracket->taxAmountMin;
        }

        if ($bracket->taxAmountMax !== null && bccomp($rawAmount, $bracket->taxAmountMax, self::SCALE) > 0) {
            $rawAmount = $bracket->taxAmountMax;
        }

        return new TaxBracketResultViewData(
            baseMin: $bracket->baseMin,
            baseMax: $bracket->baseMax,
            value: $bracket->value,
            isOverridden: $bracket->isOverridden,
            taxAmountMin: $bracket->taxAmountMin,
            taxAmountMax: $bracket->taxAmountMax,
            taxablePortion: $taxablePortion,
            taxAmount: bcround($rawAmount, 0, $tax->roundingMode->nativeMode()),
        );
    }
}

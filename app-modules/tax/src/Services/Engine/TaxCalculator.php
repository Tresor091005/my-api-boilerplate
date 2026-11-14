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
 * the sum of its rounded brackets, plus its rounding adjustment when the line
 * amount already contained it.
 */
final readonly class TaxCalculator
{
    private const int SCALE = 10;

    /** Wide enough to add unrounded bracket amounts, per-unit charges included. */
    private const int EXACT_SCALE = 20;

    /**
     * @param  array<string, TaxQuantity>  $taxQuantities  line quantity for each per-unit tax, keyed by tax id
     * @param  list<string>  $includedTaxIds  the taxes the line amount already contained
     * @param  array<string, string>  $roundingAdjustments  signed minor units added to a tax after rounding, keyed by tax id
     *
     * @throws TaxException
     *
     * @return Collection<int, TaxLineResultViewData> in calculation order
     */
    public function calculate(
        CompiledTaxProfile $profile,
        string $baseAmount,
        array $taxQuantities,
        array $includedTaxIds = [],
        array $roundingAdjustments = [],
    ): Collection {
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
            $dependencyAmount = $this->dependencyAmount($tax, $amounts);
            $inputBaseAmount = $this->inputBaseAmount($tax, $baseAmount);
            $taxableBaseAmount = bcadd($inputBaseAmount, $dependencyAmount, 0);
            $taxQuantity = $tax->isChargedPerTaxUnit() ? ($taxQuantities[$tax->id] ?? null) : null;
            $brackets = collect($tax->isApplicable() ? $this->bracketAmounts($tax, $taxableBaseAmount, $taxQuantity) : [])
                ->map(fn (array $bracketAmount): TaxBracketResultViewData => new TaxBracketResultViewData(
                    baseMin: $bracketAmount['bracket']->baseMin,
                    baseMax: $bracketAmount['bracket']->baseMax,
                    value: $bracketAmount['bracket']->value,
                    taxAmountMin: $bracketAmount['bracket']->taxAmountMin,
                    taxAmountMax: $bracketAmount['bracket']->taxAmountMax,
                    taxablePortion: $bracketAmount['portion'],
                    taxAmount: $this->round($tax, $bracketAmount['amount']),
                ));
            $roundingAdjustment = $roundingAdjustments[$tax->id] ?? '0';
            $taxAmount = $brackets->reduce(
                fn (string $sum, TaxBracketResultViewData $bracket): string => bcadd($sum, $bracket->taxAmount, 0),
                $roundingAdjustment,
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
                isIncluded: in_array($tax->id, $includedTaxIds, true),
                inputBaseAmount: $inputBaseAmount,
                dependencyAmount: $dependencyAmount,
                taxableBaseAmount: $taxableBaseAmount,
                taxQuantity: $taxQuantity?->inTaxUnit(self::SCALE),
                taxUnitCode: $tax->isChargedPerTaxUnit() ? $tax->taxUnitCode : null,
                brackets: $brackets,
                dependencies: $dependencies->values(),
                roundingAdjustment: $roundingAdjustment,
                taxAmount: $taxAmount,
            ));
        }

        return $results;
    }

    /**
     * The sum of some taxes of a profile at a base, without building a result:
     * rounded as calculate() rounds it, and before any rounding. A dependency
     * outside the given taxes counts for nothing.
     *
     * @param  array<string, true>  $taxIds  the taxes to add up, keyed by tax id
     * @param  array<string, TaxQuantity>  $taxQuantities
     *
     * @throws TaxException
     *
     * @return array{rounded: string, exact: string, amounts: array<string, string>} `amounts` holds the rounded amount of each tax
     */
    public function totalOf(CompiledTaxProfile $profile, array $taxIds, string $baseAmount, array $taxQuantities): array
    {
        $amounts = [];
        $exact = '0';

        foreach ($profile->taxes as $tax) {
            if (!isset($taxIds[$tax->id])) {
                continue;
            }

            $taxAmount = '0';

            if ($tax->isApplicable()) {
                $taxableBaseAmount = bcadd($this->inputBaseAmount($tax, $baseAmount), $this->dependencyAmount($tax, $amounts), 0);
                $taxQuantity = $tax->isChargedPerTaxUnit() ? ($taxQuantities[$tax->id] ?? null) : null;

                foreach ($this->bracketAmounts($tax, $taxableBaseAmount, $taxQuantity) as $bracketAmount) {
                    $taxAmount = bcadd($taxAmount, $this->round($tax, $bracketAmount['amount']), 0);
                    $exact = bcadd($exact, $bracketAmount['amount'], self::EXACT_SCALE);
                }
            }

            $amounts[$tax->id] = $taxAmount;
        }

        return [
            'rounded' => array_reduce($amounts, fn (string $sum, string $amount): string => bcadd($sum, $amount, 0), '0'),
            'exact'   => $exact,
            'amounts' => $amounts,
        ];
    }

    private function inputBaseAmount(CompiledTax $tax, string $baseAmount): string
    {
        return $tax->baseType === TaxBaseType::AmountWithDependencies ? $baseAmount : '0';
    }

    /**
     * The signed sum of the dependencies of a tax; one not calculated counts for nothing.
     *
     * @param  array<string, string>  $amounts  tax amounts already calculated, keyed by tax id
     */
    private function dependencyAmount(CompiledTax $tax, array $amounts): string
    {
        $sum = '0';

        foreach ($tax->dependencies as $dependency) {
            $amount = $amounts[$dependency->taxId] ?? '0';
            $sum = $dependency->operator === TaxDependencyOperator::Add ? bcadd($sum, $amount, 0) : bcsub($sum, $amount, 0);
        }

        return $sum;
    }

    /**
     * The brackets a taxable base falls in, each with its taxable portion and
     * its amount before rounding, after its minimum and maximum.
     *
     * @throws TaxException
     *
     * @return list<array{bracket: CompiledBracket, portion: string, amount: string}>
     */
    private function bracketAmounts(CompiledTax $tax, string $taxableBaseAmount, ?TaxQuantity $taxQuantity): array
    {
        if (bccomp($taxableBaseAmount, '0', 0) < 0) {
            throw TaxException::negativeTaxableBase($tax->code);
        }

        if ($tax->calculationType === TaxCalculationType::Percentage && $tax->bracketMode === TaxBracketMode::Marginal) {
            $portions = [];

            foreach ($tax->brackets as $bracket) {
                if (bccomp($taxableBaseAmount, $bracket->baseMin, 0) > 0) {
                    $portions[] = ['bracket' => $bracket, 'portion' => $this->portionWithin($bracket, $taxableBaseAmount)];
                }
            }
        } else {
            $portions = [[
                'bracket' => collect($tax->brackets)->first(fn (CompiledBracket $bracket): bool => $bracket->contains($taxableBaseAmount)),
                'portion' => $taxableBaseAmount,
            ]];
        }

        return array_map(fn (array $portion): array => [
            ...$portion,
            'amount' => $this->exactAmount($tax, $portion['bracket'], $portion['portion'], $taxQuantity),
        ], $portions);
    }

    private function portionWithin(CompiledBracket $bracket, string $taxableBaseAmount): string
    {
        $upper = $bracket->baseMax === null || bccomp($taxableBaseAmount, $bracket->baseMax, 0) < 0
            ? $taxableBaseAmount
            : $bracket->baseMax;

        return bcsub($upper, $bracket->baseMin, 0);
    }

    /** The amount of one bracket before rounding, after its minimum and maximum. */
    private function exactAmount(CompiledTax $tax, CompiledBracket $bracket, string $taxablePortion, ?TaxQuantity $taxQuantity): string
    {
        $rawAmount = match (true) {
            $tax->calculationType === TaxCalculationType::Percentage => bcdiv(bcmul($taxablePortion, $bracket->value, self::SCALE), '100', self::SCALE),
            $tax->fixedMode === TaxFixedMode::PerTaxUnit             => $taxQuantity?->charge($bracket->value, self::SCALE) ?? '0',
            default                                                  => $bracket->value,
        };

        // The minimum needs something to tax: a zero amount stays zero in every mode.
        if ($bracket->taxAmountMin !== null && bccomp($rawAmount, '0', self::SCALE) > 0 && bccomp($rawAmount, $bracket->taxAmountMin, self::SCALE) < 0) {
            $rawAmount = $bracket->taxAmountMin;
        }

        if ($bracket->taxAmountMax !== null && bccomp($rawAmount, $bracket->taxAmountMax, self::SCALE) > 0) {
            $rawAmount = $bracket->taxAmountMax;
        }

        return $rawAmount;
    }

    private function round(CompiledTax $tax, string $exactAmount): string
    {
        return bcround($exactAmount, 0, $tax->roundingMode->nativeMode());
    }
}

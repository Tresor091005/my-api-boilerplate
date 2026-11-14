<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxKind;
use Lahatre\Tax\Enums\TaxRoundingMode;
use Lahatre\Tax\Exceptions\TaxException;
use LogicException;

/**
 * Finds the amount before tax of a line whose amount already contains some
 * taxes: the base that, with those taxes added, gives the amount back.
 *
 * The line amount is kept to the unit. When rounding leaves no whole base that
 * gives it exactly, one of the two nearest bases is kept and one contained tax
 * takes the difference, so that this tax is its exact value rounded with its
 * own rounding mode: `amount × rate / (100 + rate)` for a single rate.
 */
final readonly class IncludedTaxSolver
{
    private const int SCALE = 20;

    public function __construct(
        private TaxCalculator $calculator,
    ) {}

    /**
     * @param  list<CompiledTax>  $includedTaxes  accepted by IncludedTaxRules, in calculation order
     * @param  array<string, TaxQuantity>  $taxQuantities
     *
     * @throws TaxException If the amount is lower than the taxes it contains.
     */
    public function solve(CompiledTaxProfile $profile, string $amount, array $includedTaxes, array $taxQuantities): IncludedTaxSolution
    {
        $taxIds = [];

        foreach ($includedTaxes as $tax) {
            $taxIds[$tax->id] = true;
        }

        $totals = [];
        $totalAt = function (string $base) use (&$totals, $profile, $taxIds, $taxQuantities): array {
            return $totals[$base] ??= $this->calculator->totalOf($profile, $taxIds, $base, $taxQuantities);
        };
        $grossAt = fn (string $base): string => bcadd($base, $totalAt($base)['rounded'], 0);

        if (bccomp($grossAt('0'), $amount, 0) > 0) {
            throw TaxException::amountBelowIncludedTaxes($amount);
        }

        // Taxes are never negative, so a gross is never below its base: the base is at most the amount.
        if (bccomp($grossAt($amount), $amount, 0) === 0) {
            return new IncludedTaxSolution($amount, []);
        }

        // The contained taxes never decrease with the base, so the gross grows
        // with it: search the last base whose gross is at most the amount.
        $low = '0';
        $high = $amount;
        $isAtMostAmount = function (string $base) use (&$low, &$high, $grossAt, $amount): bool {
            if (bccomp($grossAt($base), $amount, 0) <= 0) {
                $low = $base;

                return true;
            }

            $high = $base;

            return false;
        };

        // A straight-line guess lands next to the answer for plain rates; doubling
        // steps then close in on it, and halving finishes whatever the grids are.
        $guess = bcdiv(bcmul($amount, $amount, 0), $grossAt($amount), 0);
        $step = '1';

        if (bccomp($guess, $low, 0) <= 0 || $isAtMostAmount($guess)) {
            while (bccomp($next = bcadd($low, $step, 0), $high, 0) < 0 && $isAtMostAmount($next)) {
                $step = bcmul($step, '2', 0);
            }
        } else {
            while (bccomp($next = bcsub($high, $step, 0), $low, 0) > 0 && !$isAtMostAmount($next)) {
                $step = bcmul($step, '2', 0);
            }
        }

        while (bccomp(bcsub($high, $low, 0), '1', 0) > 0) {
            $isAtMostAmount(bcadd($low, bcdiv(bcsub($high, $low, 0), '2', 0), 0));
        }

        $shortfall = bcsub($amount, $grossAt($low), 0);

        if ($shortfall === '0') {
            return new IncludedTaxSolution($low, []);
        }

        // No whole base gives the amount: gross($low) < amount < gross($high).
        $absorbers = $this->absorbers($includedTaxes);
        $preferred = $absorbers[0] ?? throw new LogicException('An amount containing only fixed taxes always has a whole base.');

        if ($this->roundsBaseUp($preferred->roundingMode, $amount, bcadd($low, $totalAt($low)['exact'], self::SCALE), bcadd($high, $totalAt($high)['exact'], self::SCALE))) {
            $excess = bcsub($grossAt($high), $amount, 0);
            $amounts = $totalAt($high)['amounts'];

            foreach ($absorbers as $tax) {
                if (bccomp($amounts[$tax->id], $excess, 0) >= 0) {
                    return new IncludedTaxSolution($high, [$tax->id => bcsub('0', $excess, 0)]);
                }
            }
            // No tax is large enough to give the excess back: keep the lower base.
        }

        return new IncludedTaxSolution($low, [$preferred->id => $shortfall]);
    }

    /**
     * Whether the higher of the two bases is the exact base rounded. Rounding
     * the tax up rounds the base down, and the reverse; half up compares the
     * amount with the exact grosses of the two bases, a tie going to the tax.
     */
    private function roundsBaseUp(TaxRoundingMode $roundingMode, string $amount, string $exactGrossLow, string $exactGrossHigh): bool
    {
        return match ($roundingMode) {
            TaxRoundingMode::Up     => false,
            TaxRoundingMode::Down   => true,
            TaxRoundingMode::HalfUp => bccomp(
                bcmul(bcsub($amount, $exactGrossLow, self::SCALE), '2', self::SCALE),
                bcsub($exactGrossHigh, $exactGrossLow, self::SCALE),
                self::SCALE,
            ) > 0,
        };
    }

    /**
     * The taxes that may take a rounding difference, the preferred one first:
     * percentages no other contained percentage is calculated on, so changing
     * one changes no other contained tax. The value-added tax comes first,
     * then the last calculated.
     *
     * @param  list<CompiledTax>  $includedTaxes
     * @return list<CompiledTax>
     */
    private function absorbers(array $includedTaxes): array
    {
        $percentages = array_values(array_filter(
            $includedTaxes,
            fn (CompiledTax $tax): bool => $tax->isApplicable() && $tax->calculationType === TaxCalculationType::Percentage,
        ));
        $calculatedOn = [];

        foreach ($percentages as $tax) {
            foreach ($tax->dependencies as $dependency) {
                $calculatedOn[$dependency->taxId] = true;
            }
        }

        $absorbers = array_reverse(array_values(array_filter(
            $percentages,
            fn (CompiledTax $tax): bool => !isset($calculatedOn[$tax->id]),
        )));

        usort($absorbers, fn (CompiledTax $left, CompiledTax $right): int => ($right->kind === TaxKind::Vat) <=> ($left->kind === TaxKind::Vat));

        return $absorbers;
    }
}

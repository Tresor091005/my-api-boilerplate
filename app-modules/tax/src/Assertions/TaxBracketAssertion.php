<?php

declare(strict_types=1);

namespace Lahatre\Tax\Assertions;

use Illuminate\Support\Collection;
use Lahatre\Shared\Support\Decimal;
use Lahatre\Tax\Data\TaxBracketData;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Exceptions\TaxException;

/**
 * Checks one bracket grid against the shape of its tax. The same rules apply
 * to the tax's own grid and to the custom grid of a profile line.
 */
final readonly class TaxBracketAssertion
{
    /**
     * Brackets are read in payload order and must tile [0, ∞[ exactly: one
     * open bracket for a non-progressive tax, at least two for a progressive
     * one. A per-line tax takes no amount limits, and monetary values fit the
     * currency precision except a per-unit rate.
     *
     * @param  Collection<int, TaxBracketData>  $brackets
     *
     * @throws TaxException
     */
    public function assertGrid(
        Collection $brackets,
        bool $isProgressive,
        TaxCalculationType $calculationType,
        ?TaxFixedMode $fixedMode,
        int $currencyPrecision,
    ): void {
        $count = $brackets->count();

        if (!$isProgressive && $count !== 1) {
            throw TaxException::singleOpenBracketRequired();
        }

        if ($isProgressive && $count < 2) {
            throw TaxException::progressiveBracketsRequired();
        }

        $isAmountPerLine = $calculationType === TaxCalculationType::Fixed && $fixedMode === TaxFixedMode::PerLine;
        $previous = null;

        foreach ($brackets->values() as $index => $bracket) {
            $isLast = $index === $count - 1;

            if ($previous === null && bccomp($bracket->baseMin, '0', 6) !== 0) {
                throw TaxException::bracketsMustStartAtZero();
            }

            if ($previous !== null && bccomp($bracket->baseMin, (string) $previous->baseMax, 6) !== 0) {
                throw TaxException::bracketsNotContiguous($index);
            }

            if ($bracket->baseMax === null && !$isLast) {
                throw TaxException::onlyLastBracketMayBeOpen($index);
            }

            if ($bracket->baseMax !== null && $isLast) {
                throw TaxException::lastBracketMustBeOpen($index);
            }

            if ($bracket->baseMax !== null && bccomp($bracket->baseMax, $bracket->baseMin, 6) <= 0) {
                throw TaxException::bracketBoundsInverted($index);
            }

            if (
                $bracket->taxAmountMin !== null
                && $bracket->taxAmountMax !== null
                && bccomp($bracket->taxAmountMin, $bracket->taxAmountMax, 6) > 0
            ) {
                throw TaxException::bracketAmountLimitsInverted($index);
            }

            // A per-line amount is already fixed, so a floor or a ceiling would only replace it.
            if ($isAmountPerLine && ($bracket->taxAmountMin !== null || $bracket->taxAmountMax !== null)) {
                throw TaxException::bracketAmountLimitsNotAllowed($index);
            }

            $this->assertMonetaryPrecision($bracket, $index, $isAmountPerLine, $currencyPrecision);
            $previous = $bracket;
        }
    }

    /**
     * A per-unit rate is exempt: a price per litre can be finer than the
     * currency, only the charged amount is rounded.
     *
     * @throws TaxException
     */
    protected function assertMonetaryPrecision(TaxBracketData $bracket, int $index, bool $isAmountPerLine, int $currencyPrecision): void
    {
        $monetaryValues = [
            'base_min'       => $bracket->baseMin,
            'base_max'       => $bracket->baseMax,
            'tax_amount_min' => $bracket->taxAmountMin,
            'tax_amount_max' => $bracket->taxAmountMax,
            'value'          => $isAmountPerLine ? $bracket->value : null,
        ];

        foreach ($monetaryValues as $field => $value) {
            if ($value !== null && Decimal::places($value) > $currencyPrecision) {
                throw TaxException::monetaryPrecisionExceeded($field, $index, $currencyPrecision);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

use Lahatre\Tax\Enums\TaxBracketMode;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxDependencyOperator;
use Lahatre\Tax\Exceptions\TaxException;

/**
 * Which taxes of a profile an amount may already contain. A contained tax is
 * taken out of the amount again, so it must grow smoothly with its base: a
 * jump would leave amounts no base can produce. That rules out a rate applied
 * to the whole amount past a threshold, a fixed amount changing by bracket and
 * a minimum amount. It must also be calculated without subtracting a tax, and
 * only on taxes the amount contains too.
 */
final readonly class IncludedTaxRules
{
    /**
     * The taxes of the profile among the given ones, in calculation order,
     * once an amount may contain them together. A tax that does not apply
     * (zero-rated or exempt) is always accepted, and never counts as a
     * dependency.
     *
     * @param  list<string>  $taxIds  ids outside the profile are left out
     *
     * @throws TaxException
     *
     * @return list<CompiledTax>
     */
    public function includedTaxes(CompiledTaxProfile $profile, array $taxIds): array
    {
        $taxesById = [];

        foreach ($profile->taxes as $tax) {
            $taxesById[$tax->id] = $tax;
        }

        $includedIds = array_flip($taxIds);
        $includedTaxes = array_values(array_filter(
            $profile->taxes,
            fn (CompiledTax $tax): bool => isset($includedIds[$tax->id]),
        ));

        foreach ($includedTaxes as $tax) {
            if (!$tax->isApplicable()) {
                continue;
            }

            $this->assertGrowsSmoothly($tax);

            foreach ($tax->dependencies as $dependency) {
                $dependencyTax = $taxesById[$dependency->taxId];

                if (!$dependencyTax->isApplicable()) {
                    continue;
                }

                if ($dependency->operator === TaxDependencyOperator::Subtract) {
                    throw TaxException::includedTaxSubtractsDependency($tax->code, $dependencyTax->code);
                }

                if (!isset($includedIds[$dependencyTax->id])) {
                    throw TaxException::includedTaxDependsOnAddedTax($tax->code, $dependencyTax->code);
                }
            }
        }

        return $includedTaxes;
    }

    /** @throws TaxException */
    private function assertGrowsSmoothly(CompiledTax $tax): void
    {
        if ($tax->calculationType === TaxCalculationType::Fixed) {
            // a fixed amount does not move with the base, unless brackets change it
            if ($tax->isProgressive) {
                throw TaxException::includedTaxFixedByBracket($tax->code);
            }

            return;
        }

        if ($tax->bracketMode === TaxBracketMode::WholeAmount) {
            throw TaxException::includedTaxAppliesToWholeAmount($tax->code);
        }

        foreach ($tax->brackets as $bracket) {
            $hasMinimum = $bracket->taxAmountMin !== null && bccomp($bracket->taxAmountMin, '0', 10) > 0;

            // a zero rate never reaches its minimum
            if ($hasMinimum && bccomp($bracket->value, '0', 10) > 0) {
                throw TaxException::includedTaxHasMinimum($tax->code);
            }
        }
    }
}

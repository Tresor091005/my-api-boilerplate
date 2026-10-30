<?php

declare(strict_types=1);

namespace Lahatre\Tax\Assertions;

use Illuminate\Support\Collection;
use Lahatre\Tax\Data\TaxProfileData;
use Lahatre\Tax\Data\TaxProfileLineData;
use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Enums\TaxKind;
use Lahatre\Tax\Enums\TaxTreatment;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxDependency;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Support\Decimal;

final readonly class TaxProfileAssertion
{
    /**
     * Checks a profile definition against the taxes it references: they exist
     * once each, are active when the profile is, carry overrides only under a
     * custom treatment and only on their own brackets, include at most one VAT,
     * and every dependencies-only tax finds all its dependencies in the profile.
     *
     * @param  Collection<string, Tax>  $taxes  non-deleted organization taxes referenced by the lines, keyed by id, with `brackets` and `dependencies.dependsOnTax`
     *
     * @throws TaxException
     */
    public function assertDefinition(TaxProfileData $data, Collection $taxes, int $currencyPrecision): void
    {
        $this->assertTaxesReferenced($data, $taxes);

        foreach ($data->lines as $line) {
            $this->assertBracketOverrides($line, $taxes->get($line->taxId), $currencyPrecision);
        }

        $lineTaxes = $data->lines->map(fn (TaxProfileLineData $line): Tax => $taxes->get($line->taxId));
        $vatCodes = $lineTaxes->filter(fn (Tax $tax): bool => $tax->kind === TaxKind::Vat)->pluck('code')->values()->all();

        if (count($vatCodes) > 1) {
            throw TaxException::multipleVatTaxes($vatCodes);
        }

        $this->assertDependenciesOnlyBasesComplete($lineTaxes);
    }

    /**
     * A profile used by records stays active, and its applicable per-unit taxes
     * must use a unit of every unit group those records are measured in.
     *
     * @param  Collection<string, Tax>  $taxes  keyed by id, with `taxUnit`
     * @param  Collection<int, string>  $usedUnitGroupIds
     *
     * @throws TaxException
     */
    public function assertCompatibleWithUsage(TaxProfile $profile, TaxProfileData $data, Collection $taxes, Collection $usedUnitGroupIds): void
    {
        if ($usedUnitGroupIds->isEmpty()) {
            return;
        }

        if (!$data->isActive) {
            throw TaxException::profileInUse($profile->code);
        }

        foreach ($data->lines as $line) {
            $tax = $taxes->get($line->taxId);
            $isApplicable = $line->treatment === TaxTreatment::Standard || $line->treatment === TaxTreatment::Custom;

            if (!$isApplicable || $tax->fixed_mode !== TaxFixedMode::PerTaxUnit) {
                continue;
            }

            foreach ($usedUnitGroupIds as $unitGroupId) {
                if ($tax->taxUnit?->group_id !== $unitGroupId) {
                    throw TaxException::taxUnitIncompatible($tax->code, $unitGroupId);
                }
            }
        }
    }

    /**
     * @param  Collection<int, string>  $usedUnitGroupIds
     *
     * @throws TaxException
     */
    public function assertDeletable(TaxProfile $profile, Collection $usedUnitGroupIds): void
    {
        if ($usedUnitGroupIds->isNotEmpty()) {
            throw TaxException::profileInUse($profile->code);
        }
    }

    /**
     * @param  Collection<string, Tax>  $taxes
     *
     * @throws TaxException
     */
    protected function assertTaxesReferenced(TaxProfileData $data, Collection $taxes): void
    {
        $missing = $data->lines
            ->map(fn (TaxProfileLineData $line): string => $line->taxId)
            ->reject(fn (string $taxId): bool => $taxes->has($taxId))
            ->unique()
            ->values()
            ->all();

        if ($missing !== []) {
            throw TaxException::profileTaxesNotFound($missing);
        }

        $seen = [];

        foreach ($data->lines as $line) {
            if (isset($seen[$line->taxId])) {
                throw TaxException::duplicateProfileTax($line->taxId);
            }

            $seen[$line->taxId] = true;
        }

        if (!$data->isActive) {
            return;
        }

        $inactiveCodes = $taxes->only(array_keys($seen))
            ->reject(fn (Tax $tax): bool => $tax->is_active)
            ->pluck('code')
            ->values()
            ->all();

        if ($inactiveCodes !== []) {
            throw TaxException::profileTaxesInactive($inactiveCodes);
        }
    }

    /** @throws TaxException */
    protected function assertBracketOverrides(TaxProfileLineData $line, Tax $tax, int $currencyPrecision): void
    {
        if ($line->treatment === TaxTreatment::Custom && $line->bracketOverrides->isEmpty()) {
            throw TaxException::bracketOverrideRequired($tax->code);
        }

        if ($line->treatment !== TaxTreatment::Custom && $line->bracketOverrides->isNotEmpty()) {
            throw TaxException::bracketOverrideNotAllowed($tax->code);
        }

        $bracketIds = $tax->brackets->pluck('id')->flip();
        $seen = [];

        foreach ($line->bracketOverrides as $override) {
            if (!$bracketIds->has($override->taxBracketId)) {
                throw TaxException::bracketNotOwnedByTax($override->taxBracketId);
            }

            if (isset($seen[$override->taxBracketId])) {
                throw TaxException::duplicateBracketOverride($override->taxBracketId);
            }

            if ($tax->calculation_type === TaxCalculationType::Fixed && Decimal::places($override->value) > $currencyPrecision) {
                throw TaxException::overridePrecisionExceeded($tax->code, $currencyPrecision);
            }

            $seen[$override->taxBracketId] = true;
        }
    }

    /**
     * @param  Collection<int, Tax>  $lineTaxes
     *
     * @throws TaxException
     */
    protected function assertDependenciesOnlyBasesComplete(Collection $lineTaxes): void
    {
        $taxIdsInProfile = $lineTaxes->pluck('id')->flip();

        foreach ($lineTaxes as $tax) {
            if ($tax->base_type !== TaxBaseType::DependenciesOnly) {
                continue;
            }

            $missingCodes = $tax->dependencies
                ->reject(fn (TaxDependency $dependency): bool => $taxIdsInProfile->has($dependency->depends_on_tax_id))
                ->map(fn (TaxDependency $dependency): string => $dependency->dependsOnTax->code)
                ->values()
                ->all();

            if ($missingCodes !== []) {
                throw TaxException::dependenciesMissingFromProfile($tax->code, $missingCodes);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Tax\Assertions;

use Illuminate\Support\Collection;
use Lahatre\Tax\Data\TaxProfileData;
use Lahatre\Tax\Data\TaxProfileLineData;
use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Enums\TaxKind;
use Lahatre\Tax\Enums\TaxTreatment;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxDependency;
use Lahatre\Tax\Models\TaxProfile;

final readonly class TaxProfileAssertion
{
    public function __construct(
        private TaxBracketAssertion $bracketAssertion,
    ) {}

    /**
     * Checks a profile definition against the taxes it references: they exist
     * once each, are active when the profile is, carry a valid grid exactly
     * when the treatment is custom, include at most one VAT, and every
     * dependencies-only tax finds all its dependencies in the profile.
     *
     * @param  Collection<string, Tax>  $taxes  non-deleted organization taxes referenced by the lines, keyed by id, with `dependencies.dependsOnTax`
     *
     * @throws TaxException
     */
    public function assertDefinition(TaxProfileData $data, Collection $taxes, int $currencyPrecision): void
    {
        $this->assertTaxesReferenced($data, $taxes);

        foreach ($data->lines as $line) {
            $this->assertCustomGrid($line, $taxes->get($line->taxId), $currencyPrecision);
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

    /**
     * A custom treatment brings its own grid, checked against the shape of the
     * tax like the tax's own grid; other treatments use the tax's grid.
     *
     * @throws TaxException
     */
    protected function assertCustomGrid(TaxProfileLineData $line, Tax $tax, int $currencyPrecision): void
    {
        if ($line->treatment !== TaxTreatment::Custom) {
            if ($line->brackets->isNotEmpty()) {
                throw TaxException::customBracketsNotAllowed($tax->code);
            }

            return;
        }

        if ($line->brackets->isEmpty()) {
            throw TaxException::customBracketsRequired($tax->code);
        }

        $this->bracketAssertion->assertGrid($line->brackets, $tax->is_progressive, $tax->calculation_type, $tax->fixed_mode, $currencyPrecision);
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

<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Illuminate\Support\Collection;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Unit;
use Lahatre\Tax\Contracts\TaxInterface;
use Lahatre\Tax\Data\TaxableLineData;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Services\Engine\CompiledTax;
use Lahatre\Tax\Services\Engine\CompiledTaxProfile;
use Lahatre\Tax\Services\Engine\CompiledTaxProfileCache;
use Lahatre\Tax\Services\Engine\TaxCalculator;
use Lahatre\Tax\Services\Engine\TaxProfileCompiler;
use Lahatre\Tax\Services\Engine\TaxQuantity;
use Lahatre\Tax\Support\Decimal;
use Lahatre\Tax\ViewData\TaxLineResultViewData;
use Lahatre\Tax\ViewData\TaxResultViewData;

final readonly class TaxResolutionService implements TaxInterface
{
    public function __construct(
        private CompiledTaxProfileCache $profileCache,
        private TaxProfileCompiler $profileCompiler,
        private TaxCalculator $calculator,
        private TaxConfigurationLock $configurationLock,
        private FunctionalCurrencyResolver $currencyResolver,
        private MasterInterface $masterInterface,
    ) {}

    public function assertProfileAssignable(string $taxProfileId, Collection $unitGroupIds): void
    {
        $this->assertProfilesAssignable(collect([$taxProfileId => $unitGroupIds]));
    }

    /**
     * Takes the shared configuration lock in the caller's transaction and reads
     * the profiles from the database, bypassing the cache: a concurrent
     * deactivation either waits for the assignment and then sees it, or has
     * committed and is seen here, even before its cache invalidation runs.
     */
    public function assertProfilesAssignable(Collection $unitGroupIdsByProfile): void
    {
        if ($unitGroupIdsByProfile->isEmpty()) {
            return;
        }

        $organizationId = currentOrganizationId();
        $this->configurationLock->acquireShared($organizationId);
        $profiles = $this->profileCompiler->compile($organizationId, $unitGroupIdsByProfile->keys());

        foreach ($unitGroupIdsByProfile as $taxProfileId => $unitGroupIds) {
            $profile = $this->activeProfile($profiles, (string) $taxProfileId);

            foreach ($profile->applicableTaxesPerTaxUnit() as $tax) {
                foreach ($unitGroupIds->unique() as $unitGroupId) {
                    if ($tax->taxUnitGroupId !== $unitGroupId) {
                        throw TaxException::taxUnitIncompatible($tax->code, $unitGroupId);
                    }
                }
            }
        }
    }

    public function resolve(TaxableLineData $line): TaxResultViewData
    {
        /** @var TaxResultViewData */
        return $this->resolveMany(collect([$line]))->first();
    }

    public function resolveMany(Collection $lines): Collection
    {
        if ($lines->isEmpty()) {
            return collect();
        }

        $organizationId = currentOrganizationId();
        $functionalCurrencyCode = $this->currencyResolver->forOrganization($organizationId)->code;
        $lines->each(fn (TaxableLineData $line) => $this->assertLine($line, $functionalCurrencyCode));

        $profiles = $this->compiledProfiles(
            $organizationId,
            $lines
                ->reject(fn (TaxableLineData $line): bool => $line->taxProfileId === null)
                ->map(fn (TaxableLineData $line): string => (string) $line->taxProfileId)
                ->unique()
                ->values(),
        );
        $units = $this->units($lines, $profiles);

        return $lines->map(fn (TaxableLineData $line): TaxResultViewData => $this->resolveLine($line, $profiles, $units));
    }

    /**
     * @param  Collection<int, string>  $profileIds
     * @return Collection<string, CompiledTaxProfile>
     */
    private function compiledProfiles(string $organizationId, Collection $profileIds): Collection
    {
        if ($profileIds->isEmpty()) {
            return collect();
        }

        return $this->profileCache->many(
            $organizationId,
            $profileIds,
            fn (Collection $missingIds): Collection => $this->profileCompiler->compile($organizationId, $missingIds),
        );
    }

    /** @throws TaxException */
    private function assertLine(TaxableLineData $line, string $functionalCurrencyCode): void
    {
        if ($line->currencyCode !== $functionalCurrencyCode) {
            throw TaxException::currencyMismatch($line->currencyCode, $functionalCurrencyCode);
        }

        if (preg_match('/^\d{1,18}$/', $line->baseAmount) !== 1) {
            throw TaxException::invalidBaseAmount($line->baseAmount);
        }

        if (preg_match('/^\d{1,12}(\.\d{1,10})?$/', $line->quantity) !== 1 || bccomp($line->quantity, '0', 10) <= 0) {
            throw TaxException::invalidQuantity($line->quantity);
        }
    }

    /**
     * @param  Collection<int|string, TaxableLineData>  $lines
     * @param  Collection<string, CompiledTaxProfile>  $profiles
     * @return Collection<string, Unit> sale and tax units needed by per-unit taxes, keyed by code
     */
    private function units(Collection $lines, Collection $profiles): Collection
    {
        $codes = collect();

        foreach ($lines as $line) {
            $taxes = $line->taxProfileId === null ? [] : ($profiles->get($line->taxProfileId)?->applicableTaxesPerTaxUnit() ?? []);

            if ($taxes === [] || $line->unitCode === null) {
                continue;
            }

            $codes->push($line->unitCode, ...array_map(fn (CompiledTax $tax): string => (string) $tax->taxUnitCode, $taxes));
        }

        return $codes->isEmpty() ? collect() : $this->masterInterface->units($codes->unique()->values());
    }

    /**
     * @param  Collection<string, CompiledTaxProfile>  $profiles
     * @param  Collection<string, Unit>  $units
     *
     * @throws TaxException
     */
    private function resolveLine(TaxableLineData $line, Collection $profiles, Collection $units): TaxResultViewData
    {
        if ($line->taxProfileId === null) {
            return $this->result($line, collect());
        }

        $profile = $this->activeProfile($profiles, $line->taxProfileId);
        $inactiveTaxes = $profile->inactiveTaxes();

        if ($inactiveTaxes !== []) {
            throw TaxException::profileTaxesInactive(array_map(fn (CompiledTax $tax): string => $tax->code, $inactiveTaxes));
        }

        return $this->result($line, $this->calculator->calculate(
            $profile,
            $line->baseAmount,
            $this->taxQuantities($line, $profile, $units),
        ));
    }

    /**
     * @param  Collection<string, CompiledTaxProfile>  $profiles
     *
     * @throws TaxException
     */
    private function activeProfile(Collection $profiles, string $taxProfileId): CompiledTaxProfile
    {
        $profile = $profiles->get($taxProfileId) ?? throw TaxException::profileNotFound($taxProfileId);

        if (!$profile->isActive) {
            throw TaxException::profileInactive($profile->code);
        }

        return $profile;
    }

    /**
     * Pairs the sold quantity with the ratios of the sale unit and of each
     * applicable per-unit tax unit.
     *
     * @param  Collection<string, Unit>  $units
     *
     * @throws TaxException
     *
     * @return array<string, TaxQuantity> keyed by tax id
     */
    private function taxQuantities(TaxableLineData $line, CompiledTaxProfile $profile, Collection $units): array
    {
        $quantities = [];

        foreach ($profile->applicableTaxesPerTaxUnit() as $tax) {
            if ($line->unitCode === null) {
                throw TaxException::saleUnitRequired($tax->code);
            }

            $saleUnit = $units->get($line->unitCode);

            if ($saleUnit === null || $saleUnit->group_id !== $line->unitGroupId) {
                throw TaxException::saleUnitIncompatible($line->unitCode);
            }

            if ($tax->taxUnitGroupId !== $line->unitGroupId) {
                throw TaxException::taxUnitIncompatible($tax->code, $line->unitGroupId);
            }

            $taxUnit = $units->get((string) $tax->taxUnitCode) ?? throw TaxException::taxUnitUnavailable((string) $tax->taxUnitCode);
            $quantities[$tax->id] = new TaxQuantity($line->quantity, $saleUnit->ratio, $taxUnit->ratio);
        }

        return $quantities;
    }

    /** @param Collection<int, TaxLineResultViewData> $taxes */
    private function result(TaxableLineData $line, Collection $taxes): TaxResultViewData
    {
        return new TaxResultViewData(
            taxProfileId: $line->taxProfileId,
            currencyCode: $line->currencyCode,
            baseAmount: $line->baseAmount,
            quantity: Decimal::normalize($line->quantity),
            unitCode: $line->unitCode,
            taxes: $taxes,
            totalTaxAmount: $taxes->reduce(
                fn (string $sum, TaxLineResultViewData $tax): string => bcadd($sum, $tax->taxAmount, 0),
                '0',
            ),
        );
    }
}

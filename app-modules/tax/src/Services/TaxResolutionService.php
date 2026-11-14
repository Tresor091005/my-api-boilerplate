<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Illuminate\Support\Collection;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Unit;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Shared\Support\Decimal;
use Lahatre\Tax\Contracts\TaxInterface;
use Lahatre\Tax\Data\IncludedTaxAmountData;
use Lahatre\Tax\Data\TaxableLineData;
use Lahatre\Tax\Data\TaxSimulationData;
use Lahatre\Tax\Data\TaxSimulationLineData;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Services\Engine\CompiledTax;
use Lahatre\Tax\Services\Engine\CompiledTaxProfile;
use Lahatre\Tax\Services\Engine\CompiledTaxProfileCache;
use Lahatre\Tax\Services\Engine\IncludedTaxRules;
use Lahatre\Tax\Services\Engine\IncludedTaxSolver;
use Lahatre\Tax\Services\Engine\TaxCalculator;
use Lahatre\Tax\Services\Engine\TaxProfileCompiler;
use Lahatre\Tax\Services\Engine\TaxQuantityResolver;
use Lahatre\Tax\ViewData\TaxLineResultViewData;
use Lahatre\Tax\ViewData\TaxResultViewData;
use Lahatre\Tax\ViewData\TaxSimulationViewData;

final readonly class TaxResolutionService implements TaxInterface
{
    public function __construct(
        private CompiledTaxProfileCache $profileCache,
        private TaxProfileCompiler $profileCompiler,
        private TaxCalculator $calculator,
        private IncludedTaxRules $includedTaxRules,
        private IncludedTaxSolver $includedTaxSolver,
        private TaxQuantityResolver $quantityResolver,
        private TaxConfigurationLock $configurationLock,
        private OrganizationInterface $organizationInterface,
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

    /**
     * Reads the profile from the database under the shared configuration lock,
     * like assertProfilesAssignable(): a concurrent change of the profile
     * either waits for the amounts and then sees them, or is seen here. Each
     * amount is then calculated for one unit sold, so what a sale would refuse
     * is refused here.
     */
    public function assertTaxesIncludable(string $taxProfileId, Collection $amounts): void
    {
        if ($amounts->isEmpty()) {
            return;
        }

        $organizationId = currentOrganizationId();
        $this->configurationLock->acquireShared($organizationId);
        $profiles = $this->profileCompiler->compile($organizationId, collect([$taxProfileId]));
        $profile = $profiles->get($taxProfileId) ?? throw TaxException::profileNotFound($taxProfileId);
        $profileTaxIds = array_map(fn (CompiledTax $tax): string => $tax->id, $profile->taxes);
        $outsideProfile = $amounts
            ->flatMap(fn (IncludedTaxAmountData $amount): array => $amount->includedTaxIds)
            ->unique()
            ->reject(fn (string $taxId): bool => in_array($taxId, $profileTaxIds, true))
            ->values()
            ->all();

        if ($outsideProfile !== []) {
            throw TaxException::includedTaxesOutsideProfile($profile->code, $outsideProfile);
        }

        $currencyCode = $this->organizationInterface->functionalCurrency($organizationId)->code;
        $this->resolveLines(
            $amounts->map(fn (IncludedTaxAmountData $amount): TaxableLineData => TaxableLineData::fromArray([
                'tax_profile_id'   => $taxProfileId,
                'amount'           => $amount->amount,
                'included_tax_ids' => $amount->includedTaxIds,
                'currency_code'    => $currencyCode,
                'quantity'         => '1',
                'unit_code'        => $amount->unitCode,
            ])),
            includeInactive: false,
            profiles: $profiles,
        );
    }

    public function resolve(TaxableLineData $line): TaxResultViewData
    {
        /** @var TaxResultViewData */
        return $this->resolveMany(collect([$line]))->first();
    }

    public function resolveMany(Collection $lines): Collection
    {
        return $this->resolveLines($lines, includeInactive: false);
    }

    /**
     * Calculates lines without recording anything, in the functional
     * currency, for whoever configures profiles or prepares a sale. It applies
     * the rules of resolveMany(); with `includeInactive`, an inactive profile
     * or tax is calculated too, so a draft can be checked before it is
     * switched on.
     *
     * @throws TaxException
     */
    public function simulate(TaxSimulationData $data): TaxSimulationViewData
    {
        $currencyCode = $this->organizationInterface->functionalCurrency(currentOrganizationId())->code;
        $results = $this->resolveLines(
            $data->lines->map(fn (TaxSimulationLineData $line): TaxableLineData => TaxableLineData::fromArray([
                'tax_profile_id'   => $line->taxProfileId,
                'amount'           => $line->amount,
                'included_tax_ids' => $line->includedTaxIds,
                'currency_code'    => $currencyCode,
                'quantity'         => $line->quantity,
                'unit_code'        => $line->unitCode,
            ])),
            $data->includeInactive,
        )->values();

        return new TaxSimulationViewData(
            lines: $results,
            totalTaxAmount: $results->reduce(
                fn (string $sum, TaxResultViewData $line): string => bcadd($sum, $line->totalTaxAmount, 0),
                '0',
            ),
            totalAmount: $results->reduce(
                fn (string $sum, TaxResultViewData $line): string => bcadd($sum, $line->totalAmount, 0),
                '0',
            ),
        );
    }

    /**
     * @template TKey of array-key
     *
     * @param  Collection<TKey, TaxableLineData>  $lines
     * @param  Collection<string, CompiledTaxProfile>|null  $profiles  profiles already compiled from the database, instead of the cached ones
     *
     * @throws TaxException
     *
     * @return Collection<TKey, TaxResultViewData>
     */
    private function resolveLines(Collection $lines, bool $includeInactive, ?Collection $profiles = null): Collection
    {
        if ($lines->isEmpty()) {
            return collect();
        }

        $organizationId = currentOrganizationId();
        $functionalCurrencyCode = $this->organizationInterface->functionalCurrency($organizationId)->code;
        $lines->each(fn (TaxableLineData $line) => $this->assertLine($line, $functionalCurrencyCode));

        $profiles ??= $this->compiledProfiles(
            $organizationId,
            $lines
                ->reject(fn (TaxableLineData $line): bool => $line->taxProfileId === null)
                ->map(fn (TaxableLineData $line): string => (string) $line->taxProfileId)
                ->unique()
                ->values(),
        );
        $units = $this->units($lines, $profiles);

        return $lines->map(fn (TaxableLineData $line): TaxResultViewData => $this->resolveLine($line, $profiles, $units, $includeInactive));
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

        if (preg_match('/^\d{1,18}$/', $line->amount) !== 1) {
            throw TaxException::invalidAmount($line->amount);
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
    private function resolveLine(TaxableLineData $line, Collection $profiles, Collection $units, bool $includeInactive): TaxResultViewData
    {
        if ($line->taxProfileId === null) {
            return $this->result($line, $line->amount, collect());
        }

        $profile = $includeInactive
            ? ($profiles->get($line->taxProfileId) ?? throw TaxException::profileNotFound($line->taxProfileId))
            : $this->activeProfile($profiles, $line->taxProfileId);
        $inactiveTaxes = $profile->inactiveTaxes();

        if (!$includeInactive && $inactiveTaxes !== []) {
            throw TaxException::profileTaxesInactive(array_map(fn (CompiledTax $tax): string => $tax->code, $inactiveTaxes));
        }

        $taxQuantities = $this->quantityResolver->forLine($profile, $line->quantity, $line->unitCode, $units);

        if ($line->includedTaxIds === []) {
            return $this->result($line, $line->amount, $this->calculator->calculate($profile, $line->amount, $taxQuantities));
        }

        // The amount contains taxes: find the base it stands for, then calculate
        // every tax on that base, the taxes it does not contain coming on top.
        $includedTaxes = $this->includedTaxRules->includedTaxes($profile, $line->includedTaxIds);
        $solution = $this->includedTaxSolver->solve($profile, $line->amount, $includedTaxes, $taxQuantities);

        return $this->result($line, $solution->baseAmount, $this->calculator->calculate(
            $profile,
            $solution->baseAmount,
            $taxQuantities,
            array_map(fn (CompiledTax $tax): string => $tax->id, $includedTaxes),
            $solution->roundingAdjustments,
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

    /** @param Collection<int, TaxLineResultViewData> $taxes */
    private function result(TaxableLineData $line, string $baseAmount, Collection $taxes): TaxResultViewData
    {
        $sum = fn (Collection $taxes): string => $taxes->reduce(
            fn (string $sum, TaxLineResultViewData $tax): string => bcadd($sum, $tax->taxAmount, 0),
            '0',
        );
        [$includedTaxes, $addedTaxes] = $taxes->partition(fn (TaxLineResultViewData $tax): bool => $tax->isIncluded);
        $addedTaxAmount = $sum($addedTaxes);

        return new TaxResultViewData(
            taxProfileId: $line->taxProfileId,
            currencyCode: $line->currencyCode,
            amount: $line->amount,
            baseAmount: $baseAmount,
            quantity: Decimal::normalize($line->quantity),
            unitCode: $line->unitCode,
            taxes: $taxes,
            includedTaxAmount: $sum($includedTaxes),
            addedTaxAmount: $addedTaxAmount,
            totalTaxAmount: $sum($taxes),
            totalAmount: bcadd($line->amount, $addedTaxAmount, 0),
        );
    }
}

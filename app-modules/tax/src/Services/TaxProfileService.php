<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Tax\Assertions\TaxProfileAssertion;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;
use Lahatre\Tax\Data\TaxProfileData;
use Lahatre\Tax\Data\TaxProfileFilterData;
use Lahatre\Tax\Data\TaxProfileLineData;
use Lahatre\Tax\Data\TaxSimulationData;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Models\TaxProfileLine;
use Lahatre\Tax\Models\TaxProfileLineBracket;
use Lahatre\Tax\Services\Engine\CompiledTax;
use Lahatre\Tax\Services\Engine\CompiledTaxProfile;
use Lahatre\Tax\Services\Engine\CompiledTaxProfileCache;
use Lahatre\Tax\Services\Engine\TaxCalculator;
use Lahatre\Tax\Services\Engine\TaxProfileCompiler;
use Lahatre\Tax\Services\Engine\TaxQuantity;
use Lahatre\Tax\Support\Decimal;
use Lahatre\Tax\ViewData\TaxLineResultViewData;
use Lahatre\Tax\ViewData\TaxResultViewData;

final readonly class TaxProfileService
{
    public function __construct(
        private TaxProfileAssertion $profileAssertion,
        private TaxConfigurationLock $configurationLock,
        private FunctionalCurrencyResolver $currencyResolver,
        private CompiledTaxProfileCache $profileCache,
        private TaxProfileUsageInterface $profileUsage,
        private TaxProfileCompiler $profileCompiler,
        private TaxCalculator $calculator,
        private MasterInterface $masterInterface,
    ) {}

    public function paginate(TaxProfileFilterData $filters): CursorPaginator
    {
        $query = TaxProfile::query()
            ->where('organization_id', currentOrganizationId())
            ->when($filters->isActive !== null, fn ($query) => $query->where('is_active', $filters->isActive));

        return stableCursorPaginate($query, $filters, tieBreakerColumn: 'tax_profiles.id');
    }

    public function retrieve(TaxProfile $profile): TaxProfile
    {
        return TaxProfile::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($profile->id)
            ->firstOrFail();
    }

    /**
     * Creates a profile with its lines and bracket overrides in one
     * transaction, serialized with the other tax configuration writers.
     *
     * @throws TaxException
     */
    public function create(TaxProfileData $data): TaxProfile
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($organizationId, $data): TaxProfile {
            $this->configurationLock->acquireExclusive($organizationId);
            $taxes = $this->referencedTaxes($organizationId, $data);
            $this->profileAssertion->assertDefinition($data, $taxes, $this->currencyPrecision($organizationId));
            $this->assertCodeAvailable($organizationId, $data->code, null);

            $profile = TaxProfile::create([
                'organization_id' => $organizationId,
                'code'            => $data->code,
                'name'            => $data->name,
                'is_active'       => $data->isActive,
            ]);
            $this->writeLines($profile, $data);
            $this->profileCache->invalidateAfterCommit($organizationId);

            return $profile;
        });
    }

    /**
     * Replaces the definition of a profile. A profile assigned to records
     * stays active and compatible with their unit groups.
     *
     * @throws TaxException
     */
    public function update(TaxProfile $profile, TaxProfileData $data): TaxProfile
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($organizationId, $profile, $data): TaxProfile {
            $this->configurationLock->acquireExclusive($organizationId);
            $locked = $this->lockProfile($organizationId, $profile->id);
            $taxes = $this->referencedTaxes($organizationId, $data);

            $this->profileAssertion->assertDefinition($data, $taxes, $this->currencyPrecision($organizationId));
            $this->assertCodeAvailable($organizationId, $data->code, $locked->id);
            $this->profileAssertion->assertCompatibleWithUsage($locked, $data, $taxes, $this->usedUnitGroupIds($organizationId, $locked));

            $locked->update([
                'code'      => $data->code,
                'name'      => $data->name,
                'is_active' => $data->isActive,
            ]);
            $this->writeLines($locked, $data);
            $this->profileCache->invalidateAfterCommit($organizationId);

            return $locked->unsetRelations();
        });
    }

    /**
     * Soft-deletes a profile no record uses. Its lines and bracket overrides
     * are removed so they no longer hold the brackets of its taxes.
     *
     * @throws TaxException
     */
    public function delete(TaxProfile $profile): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($organizationId, $profile): void {
            $this->configurationLock->acquireExclusive($organizationId);
            $locked = $this->lockProfile($organizationId, $profile->id);
            $this->profileAssertion->assertDeletable($locked, $this->usedUnitGroupIds($organizationId, $locked));
            TaxProfileLine::query()
                ->where('organization_id', $organizationId)
                ->where('tax_profile_id', $locked->id)
                ->delete();
            $locked->delete();
            $this->profileCache->invalidateAfterCommit($organizationId);
        });
    }

    /**
     * Previews the taxes of one line on a profile while configuring it. Unlike
     * TaxInterface, it accepts an inactive profile or inactive taxes, so a
     * draft can be checked before it is switched on. The unit group comes
     * from the sale unit when one is given.
     *
     * @throws TaxException
     */
    public function simulate(TaxProfile $profile, TaxSimulationData $data): TaxResultViewData
    {
        $organizationId = currentOrganizationId();
        $ownedProfile = $this->retrieve($profile);
        $compiled = $this->profileCompiler->compile($organizationId, collect([$ownedProfile->id]))->get($ownedProfile->id)
            ?? throw TaxException::profileNotFound($ownedProfile->id);
        $taxes = $this->calculator->calculate($compiled, $data->baseAmount, $this->previewQuantities($compiled, $data));

        return new TaxResultViewData(
            taxProfileId: $ownedProfile->id,
            currencyCode: $this->currencyResolver->forOrganization($organizationId)->code,
            baseAmount: $data->baseAmount,
            quantity: Decimal::normalize($data->quantity),
            unitCode: $data->unitCode,
            taxes: $taxes,
            totalTaxAmount: $taxes->reduce(
                fn (string $sum, TaxLineResultViewData $tax): string => bcadd($sum, $tax->taxAmount, 0),
                '0',
            ),
        );
    }

    /**
     * Mirrors the per-unit quantities of TaxInterface: the sold quantity with
     * the ratios of the sale unit and of each applicable tax unit.
     *
     * @throws TaxException
     *
     * @return array<string, TaxQuantity> keyed by tax id
     */
    private function previewQuantities(CompiledTaxProfile $profile, TaxSimulationData $data): array
    {
        $taxes = $profile->applicableTaxesPerTaxUnit();

        if ($taxes === []) {
            return [];
        }

        if ($data->unitCode === null) {
            throw TaxException::saleUnitRequired($taxes[0]->code);
        }

        $units = $this->masterInterface->units(collect([$data->unitCode])
            ->merge(array_map(fn (CompiledTax $tax): string => (string) $tax->taxUnitCode, $taxes))
            ->unique()
            ->values());
        $saleUnit = $units->get($data->unitCode) ?? throw TaxException::saleUnitIncompatible($data->unitCode);
        $quantities = [];

        foreach ($taxes as $tax) {
            if ($tax->taxUnitGroupId !== $saleUnit->group_id) {
                throw TaxException::taxUnitIncompatible($tax->code, $saleUnit->group_id);
            }

            $taxUnit = $units->get((string) $tax->taxUnitCode) ?? throw TaxException::taxUnitUnavailable((string) $tax->taxUnitCode);
            $quantities[$tax->id] = new TaxQuantity($data->quantity, $saleUnit->ratio, $taxUnit->ratio);
        }

        return $quantities;
    }

    private function currencyPrecision(string $organizationId): int
    {
        return $this->currencyResolver->forOrganization($organizationId)->precision;
    }

    private function lockProfile(string $organizationId, string $profileId): TaxProfile
    {
        return TaxProfile::query()
            ->where('organization_id', $organizationId)
            ->whereKey($profileId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return Collection<string, Tax> */
    private function referencedTaxes(string $organizationId, TaxProfileData $data): Collection
    {
        return Tax::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $data->lines->map(fn (TaxProfileLineData $line): string => $line->taxId)->unique())
            ->with(['brackets:id,tax_id,base_min', 'dependencies.dependsOnTax:id,code', 'taxUnit:id,group_id'])
            ->get()
            ->keyBy('id');
    }

    /** @return Collection<int, string> */
    private function usedUnitGroupIds(string $organizationId, TaxProfile $profile): Collection
    {
        return $this->profileUsage
            ->unitGroupIdsByProfile($organizationId, collect([$profile->id]))
            ->get($profile->id, collect());
    }

    /** @throws TaxException */
    private function assertCodeAvailable(string $organizationId, string $code, ?string $ignoredProfileId): void
    {
        $taken = TaxProfile::query()
            ->where('organization_id', $organizationId)
            ->where('code', $code)
            ->when($ignoredProfileId, fn ($query, string $profileId) => $query->whereKeyNot($profileId))
            ->exists();

        if ($taken) {
            throw TaxException::duplicateProfileCode($code);
        }
    }

    /**
     * Replaces the lines; deleting a line cascades to its overrides.
     */
    private function writeLines(TaxProfile $profile, TaxProfileData $data): void
    {
        TaxProfileLine::query()
            ->where('organization_id', $profile->organization_id)
            ->where('tax_profile_id', $profile->id)
            ->delete();

        $now = now();
        $lines = [];
        $overrides = [];

        foreach ($data->lines as $line) {
            $lineId = (string) Str::uuid7();
            $lines[] = [
                'id'              => $lineId,
                'organization_id' => $profile->organization_id,
                'tax_profile_id'  => $profile->id,
                'tax_id'          => $line->taxId,
                'treatment'       => $line->treatment->value,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];

            foreach ($line->bracketOverrides as $override) {
                $overrides[] = [
                    'id'                  => (string) Str::uuid7(),
                    'organization_id'     => $profile->organization_id,
                    'tax_profile_line_id' => $lineId,
                    'tax_bracket_id'      => $override->taxBracketId,
                    'value'               => $override->value,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
        }

        if ($lines !== []) {
            TaxProfileLine::query()->insert($lines);
        }

        if ($overrides !== []) {
            TaxProfileLineBracket::query()->insert($overrides);
        }
    }
}

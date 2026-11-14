<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Tax\Assertions\TaxProfileAssertion;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;
use Lahatre\Tax\Data\TaxProfileData;
use Lahatre\Tax\Data\TaxProfileFilterData;
use Lahatre\Tax\Data\TaxProfileLineData;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxBracket;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Models\TaxProfileLine;
use Lahatre\Tax\Services\Engine\CompiledTaxProfileCache;

final readonly class TaxProfileService
{
    public function __construct(
        private TaxProfileAssertion $profileAssertion,
        private TaxConfigurationLock $configurationLock,
        private OrganizationInterface $organizationInterface,
        private CompiledTaxProfileCache $profileCache,
        private TaxProfileUsageInterface $profileUsage,
        private IncludedTaxUsageGuard $includedTaxUsageGuard,
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
     * Creates a profile with its lines and custom grids in one
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
     * stays active and compatible with their unit groups, and the taxes their
     * prices already contain must stay taxes a price can contain.
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
            // prices that already contain taxes of the profile must still be able to contain them
            $this->includedTaxUsageGuard->assertIncludedTaxesStillHold($organizationId, collect([$locked->id]));
            $this->profileCache->invalidateAfterCommit($organizationId);

            return $locked->unsetRelations();
        });
    }

    /**
     * Soft-deletes a profile no record uses. Its lines and their custom grids
     * are removed so they no longer hold its taxes.
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

    private function currencyPrecision(string $organizationId): int
    {
        return $this->organizationInterface->functionalCurrency($organizationId)->precision;
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
            ->with(['dependencies.dependsOnTax:id,code', 'taxUnit:id,group_id'])
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
     * Replaces the lines; deleting a line cascades to its custom grid.
     */
    private function writeLines(TaxProfile $profile, TaxProfileData $data): void
    {
        TaxProfileLine::query()
            ->where('organization_id', $profile->organization_id)
            ->where('tax_profile_id', $profile->id)
            ->delete();

        $now = now();
        $lines = [];
        $brackets = [];

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

            foreach ($line->brackets as $bracket) {
                $brackets[] = [
                    'id'                  => (string) Str::uuid7(),
                    'organization_id'     => $profile->organization_id,
                    'tax_id'              => $line->taxId,
                    'tax_profile_line_id' => $lineId,
                    'base_min'            => $bracket->baseMin,
                    'base_max'            => $bracket->baseMax,
                    'value'               => $bracket->value,
                    'tax_amount_min'      => $bracket->taxAmountMin,
                    'tax_amount_max'      => $bracket->taxAmountMax,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
        }

        if ($lines !== []) {
            TaxProfileLine::query()->insert($lines);
        }

        if ($brackets !== []) {
            TaxBracket::query()->insert($brackets);
        }
    }
}

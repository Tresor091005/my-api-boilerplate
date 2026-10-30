<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Unit;
use Lahatre\Tax\Assertions\TaxAssertion;
use Lahatre\Tax\Data\TaxBracketData;
use Lahatre\Tax\Data\TaxData;
use Lahatre\Tax\Data\TaxDependencyData;
use Lahatre\Tax\Data\TaxFilterData;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxBracket;
use Lahatre\Tax\Models\TaxDependency;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Models\TaxProfileLineBracket;
use Lahatre\Tax\Services\Engine\CompiledTaxProfileCache;

final readonly class TaxService
{
    public function __construct(
        private TaxAssertion $taxAssertion,
        private TaxConfigurationLock $configurationLock,
        private FunctionalCurrencyResolver $currencyResolver,
        private CompiledTaxProfileCache $profileCache,
        private MasterInterface $masterInterface,
    ) {}

    public function paginate(TaxFilterData $filters): CursorPaginator
    {
        $query = Tax::query()
            ->where('organization_id', currentOrganizationId())
            ->when($filters->kind, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($filters->isActive !== null, fn ($query) => $query->where('is_active', $filters->isActive));

        return stableCursorPaginate($query, $filters, tieBreakerColumn: 'tax_taxes.id');
    }

    public function retrieve(Tax $tax): Tax
    {
        return Tax::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($tax->id)
            ->firstOrFail();
    }

    /**
     * Creates a tax with its brackets and dependencies in one transaction,
     * serialized with the organization's other tax configuration writers.
     *
     * @throws TaxException
     */
    public function create(TaxData $data): Tax
    {
        $organizationId = currentOrganizationId();
        $this->taxAssertion->assertDefinition($data, $this->currencyPrecision($organizationId), null);

        return DB::transaction(function () use ($organizationId, $data): Tax {
            $this->configurationLock->acquireExclusive($organizationId);
            $taxUnit = $this->resolveTaxUnit($data);
            $this->taxAssertion->assertBracketsBelongToTax(collect(), $data);
            $this->taxAssertion->assertDependencyTargetsExist($data, $this->dependencyTaxes($organizationId, $data));
            $this->assertCodeAvailable($organizationId, $data->code, null);

            $tax = Tax::create([
                'organization_id' => $organizationId,
                ...$this->taxAttributes($data, $taxUnit),
            ]);
            $this->writeBrackets($tax, $data, collect());
            $this->writeDependencies($tax, $data);
            $this->profileCache->invalidateAfterCommit($organizationId);

            return $tax;
        });
    }

    /**
     * Replaces the definition of a tax. Brackets sent with their `id` keep
     * their identity so profile overrides stay attached; brackets left out are
     * removed unless a profile overrides them. Profiles using the tax must
     * remain valid.
     *
     * @throws TaxException
     */
    public function update(Tax $tax, TaxData $data): Tax
    {
        $organizationId = currentOrganizationId();
        $this->taxAssertion->assertDefinition($data, $this->currencyPrecision($organizationId), $tax->id);

        return DB::transaction(function () use ($organizationId, $tax, $data): Tax {
            $this->configurationLock->acquireExclusive($organizationId);
            $locked = $this->lockTax($organizationId, $tax->id)->load(['brackets', 'taxUnit']);
            $taxUnit = $this->resolveTaxUnit($data);
            $dependencyTaxes = $this->dependencyTaxes($organizationId, $data);

            $this->taxAssertion->assertDependencyTargetsExist($data, $dependencyTaxes);
            $this->assertCodeAvailable($organizationId, $data->code, $locked->id);
            $this->taxAssertion->assertBracketsBelongToTax($locked->brackets, $data);
            $this->taxAssertion->assertRemovedBracketsNotOverridden(
                $this->overriddenBracketIds($organizationId, $this->removedBracketIds($locked, $data)),
            );
            $this->assertAcyclic($organizationId, $locked, $data);
            $this->taxAssertion->assertCompatibleWithProfiles($locked, $data, $taxUnit, $this->profilesUsing($organizationId, $locked->id));

            $locked->update($this->taxAttributes($data, $taxUnit));
            $this->writeBrackets($locked, $data, $locked->brackets);
            $this->writeDependencies($locked, $data);
            $this->profileCache->invalidateAfterCommit($organizationId);

            return $locked->unsetRelations();
        });
    }

    /**
     * Soft-deletes a tax that no profile uses and no other tax depends on.
     *
     * @throws TaxException
     */
    public function delete(Tax $tax): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($organizationId, $tax): void {
            $this->configurationLock->acquireExclusive($organizationId);
            $locked = $this->lockTax($organizationId, $tax->id);
            $dependents = Tax::query()
                ->where('organization_id', $organizationId)
                ->whereHas('dependencies', fn ($query) => $query->where('depends_on_tax_id', $locked->id))
                ->get(['id', 'code']);

            $this->taxAssertion->assertDeletable($this->profilesUsing($organizationId, $locked->id), $dependents);

            TaxDependency::query()
                ->where('organization_id', $organizationId)
                ->where('tax_id', $locked->id)
                ->delete();
            $locked->delete();
            $this->profileCache->invalidateAfterCommit($organizationId);
        });
    }

    private function currencyPrecision(string $organizationId): int
    {
        return $this->currencyResolver->forOrganization($organizationId)->precision;
    }

    private function lockTax(string $organizationId, string $taxId): Tax
    {
        return Tax::query()
            ->where('organization_id', $organizationId)
            ->whereKey($taxId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function resolveTaxUnit(TaxData $data): ?Unit
    {
        return $data->taxUnitCode === null ? null : $this->masterInterface->unit($data->taxUnitCode);
    }

    /** @return Collection<string, Tax> */
    private function dependencyTaxes(string $organizationId, TaxData $data): Collection
    {
        if ($data->dependencies->isEmpty()) {
            return collect();
        }

        return Tax::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $data->dependencies->map(fn (TaxDependencyData $dependency): string => $dependency->dependsOnTaxId))
            ->get(['id', 'code'])
            ->keyBy('id');
    }

    /** @throws TaxException */
    private function assertCodeAvailable(string $organizationId, string $code, ?string $ignoredTaxId): void
    {
        $taken = Tax::query()
            ->where('organization_id', $organizationId)
            ->where('code', $code)
            ->when($ignoredTaxId, fn ($query, string $taxId) => $query->whereKeyNot($taxId))
            ->exists();

        if ($taken) {
            throw TaxException::duplicateTaxCode($code);
        }
    }

    /** @throws TaxException */
    private function assertAcyclic(string $organizationId, Tax $tax, TaxData $data): void
    {
        if ($data->dependencies->isEmpty()) {
            return;
        }

        $otherEdges = TaxDependency::query()
            ->where('organization_id', $organizationId)
            ->where('tax_id', '!=', $tax->id)
            ->whereHas('tax')
            ->get(['tax_id', 'depends_on_tax_id']);
        $codesById = Tax::query()
            ->where('organization_id', $organizationId)
            ->pluck('code', 'id');

        $this->taxAssertion->assertAcyclic($tax->id, $data, $otherEdges, $codesById);
    }

    /** @return Collection<int, string> */
    private function removedBracketIds(Tax $tax, TaxData $data): Collection
    {
        $keptIds = $data->brackets->pluck('id')->filter();

        return $tax->brackets->pluck('id')->diff($keptIds)->values();
    }

    /**
     * @param  Collection<int, string>  $bracketIds
     * @return Collection<int, string>
     */
    private function overriddenBracketIds(string $organizationId, Collection $bracketIds): Collection
    {
        if ($bracketIds->isEmpty()) {
            return collect();
        }

        return TaxProfileLineBracket::query()
            ->where('organization_id', $organizationId)
            ->whereIn('tax_bracket_id', $bracketIds)
            ->pluck('tax_bracket_id');
    }

    /** @return Collection<int, TaxProfile> */
    private function profilesUsing(string $organizationId, string $taxId): Collection
    {
        return TaxProfile::query()
            ->where('organization_id', $organizationId)
            ->whereHas('lines', fn ($query) => $query->where('tax_id', $taxId))
            ->with('lines.tax:id,code,kind')
            ->get(['id', 'code', 'is_active']);
    }

    /** @return array<string, mixed> */
    private function taxAttributes(TaxData $data, ?Unit $taxUnit): array
    {
        return [
            'code'             => $data->code,
            'name'             => $data->name,
            'kind'             => $data->kind,
            'calculation_type' => $data->calculationType,
            'fixed_mode'       => $data->fixedMode,
            'tax_unit_id'      => $taxUnit?->id,
            'base_type'        => $data->baseType,
            'is_progressive'   => $data->isProgressive,
            'bracket_mode'     => $data->bracketMode,
            'rounding_mode'    => $data->roundingMode,
            'is_active'        => $data->isActive,
        ];
    }

    /**
     * Upserts the submitted brackets and deletes the existing ones left out;
     * the deferred unique constraint tolerates bounds moving between rows.
     *
     * @param  Collection<int, TaxBracket>  $existingBrackets
     */
    private function writeBrackets(Tax $tax, TaxData $data, Collection $existingBrackets): void
    {
        $now = now();
        $rows = $data->brackets->map(fn (TaxBracketData $bracket): array => [
            'id'              => $bracket->id ?? (string) Str::uuid7(),
            'organization_id' => $tax->organization_id,
            'tax_id'          => $tax->id,
            'base_min'        => $bracket->baseMin,
            'base_max'        => $bracket->baseMax,
            'value'           => $bracket->value,
            'tax_amount_min'  => $bracket->taxAmountMin,
            'tax_amount_max'  => $bracket->taxAmountMax,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        $removedIds = $existingBrackets->pluck('id')->diff($rows->pluck('id'));

        if ($removedIds->isNotEmpty()) {
            TaxBracket::query()
                ->where('organization_id', $tax->organization_id)
                ->where('tax_id', $tax->id)
                ->whereIn('id', $removedIds)
                ->delete();
        }

        TaxBracket::query()->upsert(
            $rows->all(),
            ['id'],
            ['base_min', 'base_max', 'value', 'tax_amount_min', 'tax_amount_max', 'updated_at'],
        );
    }

    private function writeDependencies(Tax $tax, TaxData $data): void
    {
        TaxDependency::query()
            ->where('organization_id', $tax->organization_id)
            ->where('tax_id', $tax->id)
            ->delete();

        if ($data->dependencies->isEmpty()) {
            return;
        }

        $now = now();
        TaxDependency::query()->insert($data->dependencies->map(fn (TaxDependencyData $dependency): array => [
            'id'                => (string) Str::uuid7(),
            'organization_id'   => $tax->organization_id,
            'tax_id'            => $tax->id,
            'depends_on_tax_id' => $dependency->dependsOnTaxId,
            'operator'          => $dependency->operator->value,
            'created_at'        => $now,
            'updated_at'        => $now,
        ])->all());
    }
}

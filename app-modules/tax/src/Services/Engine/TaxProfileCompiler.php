<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxBracket;
use Lahatre\Tax\Models\TaxDependency;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Models\TaxProfileLine;
use Lahatre\Tax\Services\FunctionalCurrencyResolver;
use Lahatre\Tax\Services\TaxConfigurationLock;
use Lahatre\Tax\Support\Decimal;

/**
 * Loads profiles with a fixed number of queries and turns them into
 * calculation-ready structures: overrides applied, monetary values in minor
 * units, dependencies reduced to the taxes present in the profile (a missing
 * dependency of a price-based tax contributes nothing), and taxes sorted so
 * each dependency is computed before its dependents.
 */
final readonly class TaxProfileCompiler
{
    public function __construct(
        private TaxConfigurationLock $configurationLock,
        private FunctionalCurrencyResolver $currencyResolver,
    ) {}

    /**
     * The reads share one transaction under the shared configuration lock so a
     * concurrent writer cannot interleave between them.
     *
     * @param  Collection<int, string>  $profileIds
     *
     * @throws TaxException
     *
     * @return Collection<string, CompiledTaxProfile> keyed by profile id; ids outside the organization are absent
     */
    public function compile(string $organizationId, Collection $profileIds): Collection
    {
        $precision = $this->currencyResolver->forOrganization($organizationId)->precision;

        $profiles = DB::transaction(function () use ($organizationId, $profileIds): Collection {
            $this->configurationLock->acquireShared($organizationId);

            return TaxProfile::query()
                ->where('organization_id', $organizationId)
                ->whereIn('id', $profileIds)
                ->with(['lines.bracketOverrides', 'lines.tax.brackets', 'lines.tax.dependencies', 'lines.tax.taxUnit'])
                ->get();
        });

        return $profiles->mapWithKeys(fn (TaxProfile $profile): array => [
            $profile->id => $this->compileProfile($profile, $precision),
        ]);
    }

    /** @throws TaxException */
    private function compileProfile(TaxProfile $profile, int $precision): CompiledTaxProfile
    {
        $taxIdsInProfile = $profile->lines->pluck('tax_id')->flip();
        $taxes = $profile->lines->map(
            fn (TaxProfileLine $line): CompiledTax => $this->compileTax($line, $taxIdsInProfile, $precision),
        );

        return new CompiledTaxProfile(
            id: $profile->id,
            code: $profile->code,
            isActive: $profile->is_active,
            taxes: $this->inCalculationOrder($taxes),
        );
    }

    /**
     * @param  Collection<string, int>  $taxIdsInProfile
     *
     * @throws TaxException
     */
    private function compileTax(TaxProfileLine $line, Collection $taxIdsInProfile, int $precision): CompiledTax
    {
        $tax = $line->tax;
        [$present, $missing] = $tax->dependencies->partition(
            fn (TaxDependency $dependency): bool => $taxIdsInProfile->has($dependency->depends_on_tax_id),
        );

        if ($tax->base_type === TaxBaseType::DependenciesOnly && $missing->isNotEmpty()) {
            throw TaxException::dependenciesMissingFromProfile(
                $tax->code,
                Tax::query()->whereIn('id', $missing->pluck('depends_on_tax_id'))->pluck('code')->all(),
            );
        }

        $overrides = $line->bracketOverrides->keyBy('tax_bracket_id');

        return new CompiledTax(
            id: $tax->id,
            code: $tax->code,
            name: $tax->name,
            kind: $tax->kind,
            treatment: $line->treatment,
            calculationType: $tax->calculation_type,
            fixedMode: $tax->fixed_mode,
            taxUnitCode: $tax->taxUnit?->code,
            taxUnitGroupId: $tax->taxUnit?->group_id,
            baseType: $tax->base_type,
            isProgressive: $tax->is_progressive,
            bracketMode: $tax->bracket_mode,
            roundingMode: $tax->rounding_mode,
            isActive: $tax->is_active,
            brackets: $tax->brackets->map(fn (TaxBracket $bracket): CompiledBracket => new CompiledBracket(
                baseMin: $this->toMinor($bracket->base_min, $precision),
                baseMax: $bracket->base_max === null ? null : $this->toMinor($bracket->base_max, $precision),
                value: $this->compileValue($tax, $overrides->get($bracket->id)->value ?? $bracket->value, $precision),
                isOverridden: $overrides->has($bracket->id),
                taxAmountMin: $bracket->tax_amount_min === null ? null : $this->toMinor($bracket->tax_amount_min, $precision),
                taxAmountMax: $bracket->tax_amount_max === null ? null : $this->toMinor($bracket->tax_amount_max, $precision),
            ))->values()->all(),
            dependencies: $present->map(fn (TaxDependency $dependency): CompiledDependency => new CompiledDependency(
                taxId: $dependency->depends_on_tax_id,
                operator: $dependency->operator,
            ))->sortBy('taxId')->values()->all(),
        );
    }

    private function compileValue(Tax $tax, string $value, int $precision): string
    {
        return $tax->calculation_type === TaxCalculationType::Percentage
            ? Decimal::normalize($value)
            : $this->toMinor($value, $precision);
    }

    /** Exact: stored monetary values never carry more decimals than the currency precision. */
    private function toMinor(string $majorAmount, int $precision): string
    {
        return bcmul($majorAmount, bcpow('10', (string) $precision), 0);
    }

    /**
     * Kahn's topological sort; ties between independent taxes are broken by
     * code so results do not depend on database row order.
     *
     * @param  Collection<int, CompiledTax>  $taxes
     *
     * @throws TaxException
     *
     * @return list<CompiledTax>
     */
    private function inCalculationOrder(Collection $taxes): array
    {
        $byId = $taxes->keyBy('id');
        $pendingDependencies = [];
        $dependents = [];

        foreach ($taxes as $tax) {
            $pendingDependencies[$tax->id] = count($tax->dependencies);

            foreach ($tax->dependencies as $dependency) {
                $dependents[$dependency->taxId][] = $tax->id;
            }
        }

        $ready = array_keys(array_filter($pendingDependencies, fn (int $count): bool => $count === 0));
        $ordered = [];

        while ($ready !== []) {
            usort($ready, fn (string $left, string $right): int => strcmp($byId[$left]->code, $byId[$right]->code));
            $taxId = array_shift($ready);
            $ordered[] = $byId[$taxId];

            foreach ($dependents[$taxId] ?? [] as $dependentId) {
                if (--$pendingDependencies[$dependentId] === 0) {
                    $ready[] = $dependentId;
                }
            }
        }

        if (count($ordered) !== $taxes->count()) {
            throw TaxException::dependencyCycle(
                $taxes->reject(fn (CompiledTax $tax): bool => in_array($tax, $ordered, true))->pluck('code')->values()->all(),
            );
        }

        return $ordered;
    }
}

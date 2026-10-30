<?php

declare(strict_types=1);

namespace Lahatre\Tax\Assertions;

use Illuminate\Support\Collection;
use Lahatre\Master\Models\Unit;
use Lahatre\Tax\Data\TaxBracketData;
use Lahatre\Tax\Data\TaxData;
use Lahatre\Tax\Data\TaxDependencyData;
use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Enums\TaxKind;
use Lahatre\Tax\Exceptions\TaxException;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxBracket;
use Lahatre\Tax\Models\TaxDependency;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Support\Decimal;

final readonly class TaxAssertion
{
    /**
     * Checks that a definition is coherent on its own before any persisted
     * state is consulted: calculation settings, contiguous brackets starting
     * at zero, monetary precision, and the shape of its dependency list.
     *
     * @throws TaxException
     */
    public function assertDefinition(TaxData $data, int $currencyPrecision, ?string $taxId): void
    {
        $this->assertCalculationSettings($data);
        $this->assertBrackets($data, $currencyPrecision);
        $this->assertDependencyList($data, $taxId);
    }

    /**
     * @param  Collection<string, Tax>  $dependencyTaxes  non-deleted taxes of the organization keyed by id
     *
     * @throws TaxException
     */
    public function assertDependencyTargetsExist(TaxData $data, Collection $dependencyTaxes): void
    {
        $missing = $data->dependencies
            ->map(fn (TaxDependencyData $dependency): string => $dependency->dependsOnTaxId)
            ->reject(fn (string $taxId): bool => $dependencyTaxes->has($taxId))
            ->values()
            ->all();

        if ($missing !== []) {
            throw TaxException::dependencyTaxesNotFound($missing);
        }
    }

    /**
     * The rest of the organization graph is acyclic by construction, so only a
     * cycle passing through the written tax can appear.
     *
     * @param  Collection<int, TaxDependency>  $otherEdges  dependencies of every other non-deleted tax
     * @param  Collection<string, string>  $codesById
     *
     * @throws TaxException
     */
    public function assertAcyclic(string $taxId, TaxData $data, Collection $otherEdges, Collection $codesById): void
    {
        $adjacency = [];

        foreach ($otherEdges as $edge) {
            $adjacency[$edge->tax_id][] = $edge->depends_on_tax_id;
        }

        $adjacency[$taxId] = $data->dependencies
            ->map(fn (TaxDependencyData $dependency): string => $dependency->dependsOnTaxId)
            ->all();

        $cycle = $this->cycleThrough($taxId, $adjacency);

        if ($cycle !== null) {
            throw TaxException::dependencyCycle(array_map(
                fn (string $id): string => $codesById->get($id, $id),
                $cycle,
            ));
        }
    }

    /**
     * @param  Collection<int, TaxBracket>  $existingBrackets
     *
     * @throws TaxException
     */
    public function assertBracketsBelongToTax(Collection $existingBrackets, TaxData $data): void
    {
        $existingIds = $existingBrackets->pluck('id')->flip();

        foreach ($data->brackets as $bracket) {
            if ($bracket->id !== null && !$existingIds->has($bracket->id)) {
                throw TaxException::bracketNotOwnedByTax($bracket->id);
            }
        }
    }

    /**
     * @param  Collection<int, string>  $overriddenRemovedBracketIds
     *
     * @throws TaxException
     */
    public function assertRemovedBracketsNotOverridden(Collection $overriddenRemovedBracketIds): void
    {
        if ($overriddenRemovedBracketIds->isNotEmpty()) {
            throw TaxException::bracketsOverriddenByProfiles($overriddenRemovedBracketIds->unique()->values()->all());
        }
    }

    /**
     * Keeps every profile using the tax valid after the update: its calculation
     * structure cannot change, it cannot be deactivated under an active
     * profile, it cannot become a second VAT, and a dependencies-only base
     * needs all its dependencies in each profile.
     *
     * @param  Collection<int, TaxProfile>  $profiles  non-deleted profiles using the tax, with `lines.tax`
     *
     * @throws TaxException
     */
    public function assertCompatibleWithProfiles(Tax $tax, TaxData $data, ?Unit $taxUnit, Collection $profiles): void
    {
        if ($profiles->isEmpty()) {
            return;
        }

        $profileCodes = $profiles->pluck('code')->values()->all();
        $structureChanged = $tax->calculation_type !== $data->calculationType
            || $tax->fixed_mode !== $data->fixedMode
            || $tax->taxUnit?->group_id !== $taxUnit?->group_id;

        if ($structureChanged) {
            throw TaxException::taxStructureLocked($profileCodes);
        }

        $activeProfileCodes = $profiles->where('is_active', true)->pluck('code')->values()->all();

        if (!$data->isActive && $activeProfileCodes !== []) {
            throw TaxException::taxUsedByActiveProfiles($activeProfileCodes);
        }

        if ($data->kind === TaxKind::Vat) {
            foreach ($profiles as $profile) {
                $otherVatCodes = $profile->lines
                    ->filter(fn ($line): bool => $line->tax_id !== $tax->id && $line->tax->kind === TaxKind::Vat)
                    ->map(fn ($line): string => $line->tax->code)
                    ->values()
                    ->all();

                if ($otherVatCodes !== []) {
                    throw TaxException::multipleVatTaxes([$data->code, ...$otherVatCodes]);
                }
            }
        }

        if ($data->baseType === TaxBaseType::DependenciesOnly) {
            $required = $data->dependencies->map(fn (TaxDependencyData $dependency): string => $dependency->dependsOnTaxId);
            $incomplete = $profiles
                ->filter(fn (TaxProfile $profile): bool => $required->diff($profile->lines->pluck('tax_id'))->isNotEmpty())
                ->pluck('code')
                ->values()
                ->all();

            if ($incomplete !== []) {
                throw TaxException::dependenciesMissingFromProfiles($data->code, $incomplete);
            }
        }
    }

    /**
     * @param  Collection<int, TaxProfile>  $profiles  non-deleted profiles using the tax
     * @param  Collection<int, Tax>  $dependents  non-deleted taxes depending on it
     *
     * @throws TaxException
     */
    public function assertDeletable(Collection $profiles, Collection $dependents): void
    {
        if ($profiles->isNotEmpty()) {
            throw TaxException::taxUsedByProfiles($profiles->pluck('code')->values()->all());
        }

        if ($dependents->isNotEmpty()) {
            throw TaxException::taxHasDependents($dependents->pluck('code')->values()->all());
        }
    }

    /** @throws TaxException */
    protected function assertCalculationSettings(TaxData $data): void
    {
        $isFixed = $data->calculationType === TaxCalculationType::Fixed;
        $isPerTaxUnit = $data->fixedMode === TaxFixedMode::PerTaxUnit;
        $needsBracketMode = $data->isProgressive && !$isFixed;

        if ($isFixed && $data->fixedMode === null) {
            throw TaxException::fixedModeRequired();
        }

        if (!$isFixed && $data->fixedMode !== null) {
            throw TaxException::fixedModeNotAllowed();
        }

        if ($isPerTaxUnit && $data->taxUnitCode === null) {
            throw TaxException::taxUnitRequired();
        }

        if (!$isPerTaxUnit && $data->taxUnitCode !== null) {
            throw TaxException::taxUnitNotAllowed();
        }

        if ($needsBracketMode && $data->bracketMode === null) {
            throw TaxException::bracketModeRequired();
        }

        if (!$needsBracketMode && $data->bracketMode !== null) {
            throw TaxException::bracketModeNotAllowed();
        }
    }

    /**
     * Brackets are read in payload order and must tile [0, ∞[ exactly.
     *
     * @throws TaxException
     */
    protected function assertBrackets(TaxData $data, int $currencyPrecision): void
    {
        $count = $data->brackets->count();

        if (!$data->isProgressive && $count !== 1) {
            throw TaxException::singleOpenBracketRequired();
        }

        if ($data->isProgressive && $count < 2) {
            throw TaxException::progressiveBracketsRequired();
        }

        $previous = null;

        foreach ($data->brackets->values() as $index => $bracket) {
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

            $this->assertMonetaryPrecision($data, $bracket, $index, $currencyPrecision);
            $previous = $bracket;
        }
    }

    /** @throws TaxException */
    protected function assertMonetaryPrecision(TaxData $data, TaxBracketData $bracket, int $index, int $currencyPrecision): void
    {
        $monetaryValues = [
            'base_min'       => $bracket->baseMin,
            'base_max'       => $bracket->baseMax,
            'tax_amount_min' => $bracket->taxAmountMin,
            'tax_amount_max' => $bracket->taxAmountMax,
            'value'          => $data->calculationType === TaxCalculationType::Fixed ? $bracket->value : null,
        ];

        foreach ($monetaryValues as $field => $value) {
            if ($value !== null && Decimal::places($value) > $currencyPrecision) {
                throw TaxException::monetaryPrecisionExceeded($field, $index, $currencyPrecision);
            }
        }
    }

    /** @throws TaxException */
    protected function assertDependencyList(TaxData $data, ?string $taxId): void
    {
        if ($data->baseType === TaxBaseType::DependenciesOnly && $data->dependencies->isEmpty()) {
            throw TaxException::dependencyRequired();
        }

        $seen = [];

        foreach ($data->dependencies as $dependency) {
            if ($dependency->dependsOnTaxId === $taxId) {
                throw TaxException::selfDependency();
            }

            if (isset($seen[$dependency->dependsOnTaxId])) {
                throw TaxException::duplicateDependency($dependency->dependsOnTaxId);
            }

            $seen[$dependency->dependsOnTaxId] = true;
        }
    }

    /**
     * @param  array<string, list<string>>  $adjacency
     * @return list<string>|null the tax ids of the cycle, starting and ending at $origin
     */
    protected function cycleThrough(string $origin, array $adjacency): ?array
    {
        $stack = [[$origin, [$origin]]];
        $visited = [];

        while ($stack !== []) {
            [$node, $path] = array_pop($stack);

            foreach ($adjacency[$node] ?? [] as $next) {
                if ($next === $origin) {
                    return [...$path, $origin];
                }

                if (isset($visited[$next])) {
                    continue;
                }

                $visited[$next] = true;
                $stack[] = [$next, [...$path, $next]];
            }
        }

        return null;
    }
}

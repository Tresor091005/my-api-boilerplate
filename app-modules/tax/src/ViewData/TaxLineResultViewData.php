<?php

declare(strict_types=1);

namespace Lahatre\Tax\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;
use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxBracketMode;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Enums\TaxKind;
use Lahatre\Tax\Enums\TaxRoundingMode;
use Lahatre\Tax\Enums\TaxTreatment;

/**
 * One tax of a result. `taxable_base_amount = input_base_amount + dependency_amount`
 * and `dependency_amount` is the signed sum of `dependencies[].tax_amount`.
 *
 * @phpstan-import-type TaxBracketResult from TaxBracketResultViewData
 * @phpstan-import-type TaxDependencyResult from TaxDependencyResultViewData
 *
 * @phpstan-type TaxLineResult array{tax_id: string, code: string, name: string, kind: string, treatment: string, calculation_type: string, fixed_mode: string|null, is_progressive: bool, bracket_mode: string|null, base_type: string, rounding_mode: string, input_base_amount: string, dependency_amount: string, taxable_base_amount: string, tax_quantity: string|null, tax_unit_code: string|null, brackets: list<TaxBracketResult>, dependencies: list<TaxDependencyResult>, tax_amount: string}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TaxLineResultViewData implements Arrayable, JsonSerializable
{
    /**
     * @param  Collection<int, TaxBracketResultViewData>  $brackets
     * @param  Collection<int, TaxDependencyResultViewData>  $dependencies
     */
    public function __construct(
        public string $taxId,
        public string $code,
        public string $name,
        public TaxKind $kind,
        public TaxTreatment $treatment,
        public TaxCalculationType $calculationType,
        public ?TaxFixedMode $fixedMode,
        public bool $isProgressive,
        public ?TaxBracketMode $bracketMode,
        public TaxBaseType $baseType,
        public TaxRoundingMode $roundingMode,
        public string $inputBaseAmount,
        public string $dependencyAmount,
        public string $taxableBaseAmount,
        public ?string $taxQuantity,
        public ?string $taxUnitCode,
        public Collection $brackets,
        public Collection $dependencies,
        public string $taxAmount,
    ) {}

    /** @return TaxLineResult */
    public function toArray(): array
    {
        return [
            'tax_id'              => $this->taxId,
            'code'                => $this->code,
            'name'                => $this->name,
            'kind'                => $this->kind->value,
            'treatment'           => $this->treatment->value,
            'calculation_type'    => $this->calculationType->value,
            'fixed_mode'          => $this->fixedMode?->value,
            'is_progressive'      => $this->isProgressive,
            'bracket_mode'        => $this->bracketMode?->value,
            'base_type'           => $this->baseType->value,
            'rounding_mode'       => $this->roundingMode->value,
            'input_base_amount'   => $this->inputBaseAmount,
            'dependency_amount'   => $this->dependencyAmount,
            'taxable_base_amount' => $this->taxableBaseAmount,
            'tax_quantity'        => $this->taxQuantity,
            'tax_unit_code'       => $this->taxUnitCode,
            'brackets'            => $this->brackets->map(fn (TaxBracketResultViewData $bracket): array => $bracket->toArray())->values()->all(),
            'dependencies'        => $this->dependencies->map(fn (TaxDependencyResultViewData $dependency): array => $dependency->toArray())->values()->all(),
            'tax_amount'          => $this->taxAmount,
        ];
    }

    /** @return TaxLineResult */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

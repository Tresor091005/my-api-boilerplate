<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxBracketMode;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Enums\TaxKind;
use Lahatre\Tax\Enums\TaxRoundingMode;
use Lahatre\Tax\Enums\TaxTreatment;

/**
 * A tax as one profile applies it: overrides merged into its brackets and
 * only the dependencies present in the profile.
 */
final readonly class CompiledTax
{
    /**
     * @param  list<CompiledBracket>  $brackets
     * @param  list<CompiledDependency>  $dependencies
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public TaxKind $kind,
        public TaxTreatment $treatment,
        public TaxCalculationType $calculationType,
        public ?TaxFixedMode $fixedMode,
        public ?string $taxUnitCode,
        public ?string $taxUnitGroupId,
        public TaxBaseType $baseType,
        public bool $isProgressive,
        public ?TaxBracketMode $bracketMode,
        public TaxRoundingMode $roundingMode,
        public bool $isActive,
        public array $brackets,
        public array $dependencies,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            code: $data['code'],
            name: $data['name'],
            kind: TaxKind::from($data['kind']),
            treatment: TaxTreatment::from($data['treatment']),
            calculationType: TaxCalculationType::from($data['calculation_type']),
            fixedMode: TaxFixedMode::tryFrom((string) $data['fixed_mode']),
            taxUnitCode: $data['tax_unit_code'],
            taxUnitGroupId: $data['tax_unit_group_id'],
            baseType: TaxBaseType::from($data['base_type']),
            isProgressive: $data['is_progressive'],
            bracketMode: TaxBracketMode::tryFrom((string) $data['bracket_mode']),
            roundingMode: TaxRoundingMode::from($data['rounding_mode']),
            isActive: $data['is_active'],
            brackets: array_map(CompiledBracket::fromArray(...), $data['brackets']),
            dependencies: array_map(CompiledDependency::fromArray(...), $data['dependencies']),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'name'              => $this->name,
            'kind'              => $this->kind->value,
            'treatment'         => $this->treatment->value,
            'calculation_type'  => $this->calculationType->value,
            'fixed_mode'        => $this->fixedMode?->value,
            'tax_unit_code'     => $this->taxUnitCode,
            'tax_unit_group_id' => $this->taxUnitGroupId,
            'base_type'         => $this->baseType->value,
            'is_progressive'    => $this->isProgressive,
            'bracket_mode'      => $this->bracketMode?->value,
            'rounding_mode'     => $this->roundingMode->value,
            'is_active'         => $this->isActive,
            'brackets'          => array_map(fn (CompiledBracket $bracket): array => $bracket->toArray(), $this->brackets),
            'dependencies'      => array_map(fn (CompiledDependency $dependency): array => $dependency->toArray(), $this->dependencies),
        ];
    }

    public function isApplicable(): bool
    {
        return $this->treatment === TaxTreatment::Standard || $this->treatment === TaxTreatment::Custom;
    }

    public function isChargedPerTaxUnit(): bool
    {
        return $this->fixedMode === TaxFixedMode::PerTaxUnit;
    }
}

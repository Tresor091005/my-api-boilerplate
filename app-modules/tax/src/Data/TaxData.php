<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

use Illuminate\Support\Collection;
use Lahatre\Tax\Enums\TaxBaseType;
use Lahatre\Tax\Enums\TaxBracketMode;
use Lahatre\Tax\Enums\TaxCalculationType;
use Lahatre\Tax\Enums\TaxFixedMode;
use Lahatre\Tax\Enums\TaxKind;
use Lahatre\Tax\Enums\TaxRoundingMode;

/**
 * Complete tax definition; an update replaces the brackets and dependencies.
 */
final readonly class TaxData
{
    /**
     * @param  Collection<int, TaxBracketData>  $brackets
     * @param  Collection<int, TaxDependencyData>  $dependencies
     */
    private function __construct(
        public string $code,
        public string $name,
        public TaxKind $kind,
        public TaxCalculationType $calculationType,
        public ?TaxFixedMode $fixedMode,
        public ?string $taxUnitCode,
        public TaxBaseType $baseType,
        public bool $isProgressive,
        public ?TaxBracketMode $bracketMode,
        public TaxRoundingMode $roundingMode,
        public bool $isActive,
        public Collection $brackets,
        public Collection $dependencies,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) $data['code'],
            name: (string) $data['name'],
            kind: TaxKind::from((string) ($data['kind'] ?? TaxKind::Other->value)),
            calculationType: TaxCalculationType::from((string) $data['calculation_type']),
            fixedMode: isset($data['fixed_mode']) ? TaxFixedMode::from((string) $data['fixed_mode']) : null,
            taxUnitCode: isset($data['tax_unit_code']) ? (string) $data['tax_unit_code'] : null,
            baseType: TaxBaseType::from((string) ($data['base_type'] ?? TaxBaseType::AmountWithDependencies->value)),
            isProgressive: (bool) ($data['is_progressive'] ?? false),
            bracketMode: isset($data['bracket_mode']) ? TaxBracketMode::from((string) $data['bracket_mode']) : null,
            roundingMode: TaxRoundingMode::from((string) ($data['rounding_mode'] ?? TaxRoundingMode::HalfUp->value)),
            isActive: (bool) ($data['is_active'] ?? true),
            brackets: collect($data['brackets'])->map(TaxBracketData::fromArray(...))->values(),
            dependencies: collect($data['dependencies'] ?? [])->map(TaxDependencyData::fromArray(...))->values(),
        );
    }
}

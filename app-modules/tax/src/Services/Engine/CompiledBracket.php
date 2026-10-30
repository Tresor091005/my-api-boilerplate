<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

/**
 * An effective bracket: monetary bounds, limits and fixed values are minor
 * units of the functional currency; a percentage value stays a decimal.
 */
final readonly class CompiledBracket
{
    public function __construct(
        public string $baseMin,
        public ?string $baseMax,
        public string $value,
        public bool $isOverridden,
        public ?string $taxAmountMin,
        public ?string $taxAmountMax,
    ) {}

    /** @param array{base_min: string, base_max: string|null, value: string, is_overridden: bool, tax_amount_min: string|null, tax_amount_max: string|null} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            baseMin: $data['base_min'],
            baseMax: $data['base_max'],
            value: $data['value'],
            isOverridden: $data['is_overridden'],
            taxAmountMin: $data['tax_amount_min'],
            taxAmountMax: $data['tax_amount_max'],
        );
    }

    /** @return array{base_min: string, base_max: string|null, value: string, is_overridden: bool, tax_amount_min: string|null, tax_amount_max: string|null} */
    public function toArray(): array
    {
        return [
            'base_min'       => $this->baseMin,
            'base_max'       => $this->baseMax,
            'value'          => $this->value,
            'is_overridden'  => $this->isOverridden,
            'tax_amount_min' => $this->taxAmountMin,
            'tax_amount_max' => $this->taxAmountMax,
        ];
    }

    public function contains(string $amount): bool
    {
        return bccomp($amount, $this->baseMin, 10) >= 0
            && ($this->baseMax === null || bccomp($amount, $this->baseMax, 10) < 0);
    }
}

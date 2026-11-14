<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

/**
 * One bracket of a grid: the tax's own grid or the custom grid of a profile line.
 */
final readonly class TaxBracketData
{
    private function __construct(
        public string $baseMin,
        public ?string $baseMax,
        public string $value,
        public ?string $taxAmountMin,
        public ?string $taxAmountMax,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            baseMin: (string) $data['base_min'],
            baseMax: isset($data['base_max']) ? (string) $data['base_max'] : null,
            value: (string) $data['value'],
            taxAmountMin: isset($data['tax_amount_min']) ? (string) $data['tax_amount_min'] : null,
            taxAmountMax: isset($data['tax_amount_max']) ? (string) $data['tax_amount_max'] : null,
        );
    }
}

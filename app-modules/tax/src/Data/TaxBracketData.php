<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

final readonly class TaxBracketData
{
    private function __construct(
        public ?string $id,
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
            id: isset($data['id']) ? (string) $data['id'] : null,
            baseMin: (string) $data['base_min'],
            baseMax: isset($data['base_max']) ? (string) $data['base_max'] : null,
            value: (string) $data['value'],
            taxAmountMin: isset($data['tax_amount_min']) ? (string) $data['tax_amount_min'] : null,
            taxAmountMax: isset($data['tax_amount_max']) ? (string) $data['tax_amount_max'] : null,
        );
    }
}

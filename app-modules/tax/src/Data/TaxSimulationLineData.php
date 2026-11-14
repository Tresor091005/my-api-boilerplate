<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

final readonly class TaxSimulationLineData
{
    private function __construct(
        public string $taxProfileId,
        public string $baseAmount,
        public string $quantity,
        public ?string $unitCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            taxProfileId: (string) $data['tax_profile_id'],
            baseAmount: (string) $data['base_amount'],
            quantity: (string) $data['quantity'],
            unitCode: isset($data['unit_code']) ? (string) $data['unit_code'] : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

final readonly class TaxSimulationData
{
    private function __construct(
        public string $baseAmount,
        public string $quantity,
        public ?string $unitCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            baseAmount: (string) $data['base_amount'],
            quantity: (string) $data['quantity'],
            unitCode: isset($data['unit_code']) ? (string) $data['unit_code'] : null,
        );
    }
}

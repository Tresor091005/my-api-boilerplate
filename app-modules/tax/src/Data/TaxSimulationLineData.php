<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

final readonly class TaxSimulationLineData
{
    /** @param list<string> $includedTaxIds the taxes the amount already contains */
    private function __construct(
        public string $taxProfileId,
        public string $amount,
        public array $includedTaxIds,
        public string $quantity,
        public ?string $unitCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            taxProfileId: (string) $data['tax_profile_id'],
            amount: (string) $data['amount'],
            includedTaxIds: array_values(array_unique(array_map(strval(...), $data['included_tax_ids'] ?? []))),
            quantity: (string) $data['quantity'],
            unitCode: isset($data['unit_code']) ? (string) $data['unit_code'] : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

/**
 * An amount for one unit sold that already contains taxes, such as a price:
 * minor units of the organization functional currency, the taxes it contains
 * and the unit it is sold in.
 */
final readonly class IncludedTaxAmountData
{
    /** @param list<string> $includedTaxIds */
    private function __construct(
        public string $amount,
        public array $includedTaxIds,
        public ?string $unitCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (string) $data['amount'],
            includedTaxIds: array_values(array_unique(array_map(strval(...), $data['included_tax_ids']))),
            unitCode: isset($data['unit_code']) ? (string) $data['unit_code'] : null,
        );
    }
}

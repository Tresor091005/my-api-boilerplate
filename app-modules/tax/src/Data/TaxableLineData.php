<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

/**
 * One taxable line handed to the engine: the amount after discounts in minor
 * units of the organization functional currency, and the quantity actually sold.
 */
final readonly class TaxableLineData
{
    private function __construct(
        public ?string $taxProfileId,
        public string $unitGroupId,
        public string $baseAmount,
        public string $currencyCode,
        public string $quantity,
        public ?string $unitCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            taxProfileId: isset($data['tax_profile_id']) ? (string) $data['tax_profile_id'] : null,
            unitGroupId: (string) $data['unit_group_id'],
            baseAmount: (string) $data['base_amount'],
            currencyCode: (string) $data['currency_code'],
            quantity: (string) $data['quantity'],
            unitCode: isset($data['unit_code']) ? (string) $data['unit_code'] : null,
        );
    }
}

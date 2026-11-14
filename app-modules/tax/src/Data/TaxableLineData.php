<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

/**
 * One taxable line handed to the engine: its amount after discounts in minor
 * units of the organization functional currency, the taxes that amount
 * already contains, and the quantity actually sold. Without contained taxes
 * the amount is the base every tax is calculated on; a contained tax outside
 * the profile is left out.
 */
final readonly class TaxableLineData
{
    /** @param list<string> $includedTaxIds */
    private function __construct(
        public ?string $taxProfileId,
        public string $amount,
        public array $includedTaxIds,
        public string $currencyCode,
        public string $quantity,
        public ?string $unitCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            taxProfileId: isset($data['tax_profile_id']) ? (string) $data['tax_profile_id'] : null,
            amount: (string) $data['amount'],
            includedTaxIds: array_values(array_unique(array_map(strval(...), $data['included_tax_ids'] ?? []))),
            currencyCode: (string) $data['currency_code'],
            quantity: (string) $data['quantity'],
            unitCode: isset($data['unit_code']) ? (string) $data['unit_code'] : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

/**
 * One price to add to an item; the amount is a decimal string in the
 * organization's functional currency ("1500", "12.50").
 */
final readonly class CatalogItemPriceData
{
    private function __construct(
        public string $priceLevelId,
        public string $unitCode,
        public string $amount,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            priceLevelId: (string) $data['price_level_id'],
            unitCode: (string) $data['unit_code'],
            amount: (string) $data['amount'],
        );
    }
}

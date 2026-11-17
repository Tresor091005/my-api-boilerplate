<?php

declare(strict_types=1);

namespace Lahatre\Sale\Data;

final readonly class SaleLineData
{
    private function __construct(
        public string $catalogItemId,
        public string $unitCode,
        public int $quantity,
        public string $priceLevelId,
        public string $unitAmount,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            catalogItemId: (string) $data['catalog_item_id'],
            unitCode: (string) $data['unit_code'],
            quantity: (int) $data['quantity'],
            priceLevelId: (string) $data['price_level_id'],
            unitAmount: (string) $data['unit_amount'],
        );
    }
}

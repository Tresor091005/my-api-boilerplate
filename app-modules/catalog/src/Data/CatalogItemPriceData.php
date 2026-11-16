<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

/**
 * One price to add to an item. The amount is a decimal string in the currency
 * of the price level ("1500", "12.50") and already contains the included
 * taxes; an empty list makes it a price before tax. Without a level, the price
 * goes to the organization's built-in level.
 */
final readonly class CatalogItemPriceData
{
    /** @param list<string> $includedTaxIds */
    private function __construct(
        public ?string $priceLevelId,
        public string $unitCode,
        public string $amount,
        public array $includedTaxIds,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            priceLevelId: isset($data['price_level_id']) ? (string) $data['price_level_id'] : null,
            unitCode: (string) $data['unit_code'],
            amount: (string) $data['amount'],
            includedTaxIds: array_values(array_unique(array_map(strval(...), $data['included_tax_ids']))),
        );
    }

    public function atPriceLevel(string $priceLevelId): self
    {
        return new self($priceLevelId, $this->unitCode, $this->amount, $this->includedTaxIds);
    }
}

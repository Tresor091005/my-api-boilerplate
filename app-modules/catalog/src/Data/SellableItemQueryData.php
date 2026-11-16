<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Catalog\ViewData\SellableItemViewData;

/**
 * One (item, unit, price level) a sale asks the catalog about.
 */
final readonly class SellableItemQueryData
{
    private function __construct(
        public string $catalogItemId,
        public string $unitCode,
        public string $priceLevelId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            catalogItemId: (string) $data['catalog_item_id'],
            unitCode: (string) $data['unit_code'],
            priceLevelId: (string) $data['price_level_id'],
        );
    }

    /** The key of the answer to this request. */
    public function key(): string
    {
        return SellableItemViewData::key($this->catalogItemId, $this->unitCode, $this->priceLevelId);
    }
}

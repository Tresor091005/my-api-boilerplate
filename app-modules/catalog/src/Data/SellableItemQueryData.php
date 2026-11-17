<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Catalog\ViewData\SellableItemViewData;

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

    public function key(): string
    {
        return SellableItemViewData::key($this->catalogItemId, $this->unitCode, $this->priceLevelId);
    }
}

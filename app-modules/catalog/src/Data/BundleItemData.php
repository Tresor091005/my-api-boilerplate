<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Catalog\Enums\CatalogItemType;

final readonly class BundleItemData
{
    private function __construct(
        public CatalogItemType $itemType,
        public string $itemId,
        public int $quantity,
        public string $unitCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            itemType: CatalogItemType::from($data['item_type']),
            itemId: (string) $data['item_id'],
            quantity: (int) $data['quantity'],
            unitCode: (string) $data['unit_code'],
        );
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

final readonly class InventoryLocationValueFilterData
{
    /** @param  array<int, string>|null  $itemId */
    private function __construct(
        public ?array $itemId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            itemId: $data['item_id'] ?? null,
        );
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

final readonly class InventoryStockExpiringFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public int $days,
        public ?string $locationId,
        public ?string $itemId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 50),
            cursor: $data['cursor'] ?? null,
            days: (int) ($data['days'] ?? 7),
            locationId: $data['location_id'] ?? null,
            itemId: $data['item_id'] ?? null,
        );
    }
}

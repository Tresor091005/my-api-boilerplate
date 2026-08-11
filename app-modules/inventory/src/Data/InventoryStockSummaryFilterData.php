<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

final readonly class InventoryStockSummaryFilterData
{
    /**
     * @param  array<int, string>|null  $itemId
     * @param  array<int, string>|null  $locationId
     */
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public ?array $itemId,
        public ?array $locationId,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 50),
            cursor: $data['cursor'] ?? null,
            itemId: $data['item_id'] ?? null,
            locationId: $data['location_id'] ?? null,
        );
    }
}

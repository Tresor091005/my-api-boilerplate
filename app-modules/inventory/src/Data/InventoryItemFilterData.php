<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

final readonly class InventoryItemFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?string $sku,
        public ?bool $stockTrackingEnabled,
        public ?string $baseUnitCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'id',
            sortOrder: $data['sort_order'] ?? 'asc',
            sku: $data['sku'] ?? null,
            stockTrackingEnabled: array_key_exists('stock_tracking_enabled', $data) ? (bool) $data['stock_tracking_enabled'] : null,
            baseUnitCode: $data['base_unit_code'] ?? null,
        );
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

final readonly class InventoryItemFilterData
{
    /**
     * @param  array<int, string>|null  $ids
     * @param  array<int, string>|null  $itemableId
     */
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?array $ids,
        public ?string $itemableType,
        public ?array $itemableId,
        public ?string $sku,
        public ?bool $isActive,
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
            ids: $data['ids'] ?? null,
            itemableType: $data['itemable_type'] ?? null,
            itemableId: $data['itemable_id'] ?? null,
            sku: $data['sku'] ?? null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            baseUnitCode: $data['base_unit_code'] ?? null,
        );
    }
}

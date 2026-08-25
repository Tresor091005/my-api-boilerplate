<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

final readonly class InventoryLocationFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?bool $isActive,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'id',
            sortOrder: $data['sort_order'] ?? 'asc',
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
        );
    }
}

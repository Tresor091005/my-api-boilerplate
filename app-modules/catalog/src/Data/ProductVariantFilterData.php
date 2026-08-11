<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

final readonly class ProductVariantFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?bool $shouldManageStock,
        public ?bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortOrder: $data['sort_order'] ?? 'asc',
            shouldManageStock: array_key_exists('should_manage_stock', $data) && $data['should_manage_stock'] !== null
                ? (bool) $data['should_manage_stock']
                : null,
            isActive: array_key_exists('is_active', $data) && $data['is_active'] !== null
                ? (bool) $data['is_active']
                : null,
        );
    }
}

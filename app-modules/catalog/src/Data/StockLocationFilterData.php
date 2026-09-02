<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

final readonly class StockLocationFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?string $handle,
        public ?string $name,
        public ?bool $isActive,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'handle',
            sortOrder: $data['sort_order'] ?? 'asc',
            handle: $data['handle'] ?? null,
            name: $data['name'] ?? null,
            isActive: array_key_exists('is_active', $data) && $data['is_active'] !== null
                ? (bool) $data['is_active']
                : null,
        );
    }
}

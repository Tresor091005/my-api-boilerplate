<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

final readonly class BundleFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?string $handle,
        public ?string $name,
        public ?string $sku,
        public ?bool $isActive,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $sortBy = $data['sort_by'] ?? 'handle';

        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: match ($sortBy) {
                'sku'       => 'catalog_item_sku',
                'is_active' => 'catalog_item_is_active',
                default     => "catalog_bundles.{$sortBy}",
            },
            sortOrder: $data['sort_order'] ?? 'asc',
            handle: $data['handle'] ?? null,
            name: $data['name'] ?? null,
            sku: $data['sku'] ?? null,
            isActive: array_key_exists('is_active', $data) && $data['is_active'] !== null
                ? (bool) $data['is_active']
                : null,
        );
    }
}

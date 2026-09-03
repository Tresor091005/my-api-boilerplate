<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Catalog\Enums\BundleStockOperationStatus;
use Lahatre\Catalog\Enums\BundleStockOperationType;

final readonly class BundleStockOperationFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?BundleStockOperationType $type,
        public ?BundleStockOperationStatus $status,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: 'catalog_bundle_stock_operations.'.($data['sort_by'] ?? 'created_at'),
            sortOrder: $data['sort_order'] ?? 'desc',
            type: isset($data['type']) ? BundleStockOperationType::from($data['type']) : null,
            status: isset($data['status']) ? BundleStockOperationStatus::from($data['status']) : null,
        );
    }
}

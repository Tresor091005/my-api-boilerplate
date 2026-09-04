<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

use Lahatre\Catalog\Enums\StockTransferStatus;

final readonly class StockTransferFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?StockTransferStatus $status,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 20),
            cursor: $data['cursor'] ?? null,
            sortBy: (string) ($data['sort_by'] ?? 'created_at'),
            sortOrder: (string) ($data['sort_order'] ?? 'desc'),
            status: isset($data['status']) ? StockTransferStatus::from($data['status']) : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Sale\Data;

use Lahatre\Sale\Enums\FulfillmentStatus;

final readonly class FulfillmentFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?FulfillmentStatus $status,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortOrder: $data['sort_order'] ?? 'desc',
            status: isset($data['status']) ? FulfillmentStatus::from((string) $data['status']) : null,
        );
    }
}

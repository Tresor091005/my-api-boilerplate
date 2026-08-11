<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

final readonly class InventoryTransactionFilterData
{
    /**
     * @param  array<int, string>|null  $ids
     * @param  array<int, string>|null  $referenceId
     */
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?array $ids,
        public ?string $referenceType,
        public ?array $referenceId,
        public ?string $transactionType,
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
            referenceType: $data['reference_type'] ?? null,
            referenceId: $data['reference_id'] ?? null,
            transactionType: $data['transaction_type'] ?? null,
        );
    }
}

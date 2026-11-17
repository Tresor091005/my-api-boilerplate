<?php

declare(strict_types=1);

namespace Lahatre\Sale\Data;

use Lahatre\Sale\Enums\SaleStatus;

final readonly class SaleFilterData
{
    /** @param list<SaleStatus> $statuses */
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public array $statuses,
        public ?string $customerId,
        public ?string $number,
        public ?string $fromDate,
        public ?string $toDate,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $statuses = $data['status'] ?? [];

        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'created_at',
            sortOrder: $data['sort_order'] ?? 'desc',
            statuses: array_values(array_map(
                fn (string $status): SaleStatus => SaleStatus::from($status),
                array_unique(is_array($statuses) ? $statuses : [$statuses]),
            )),
            customerId: $data['customer_id'] ?? null,
            number: $data['number'] ?? null,
            fromDate: $data['from_date'] ?? null,
            toDate: $data['to_date'] ?? null,
        );
    }
}

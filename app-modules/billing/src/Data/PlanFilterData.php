<?php

declare(strict_types=1);

namespace Lahatre\Billing\Data;

use Lahatre\Billing\Enums\PlanStatus;

final readonly class PlanFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?string $code,
        public ?string $name,
        public ?PlanStatus $status,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'code',
            sortOrder: $data['sort_order'] ?? 'asc',
            code: $data['code'] ?? null,
            name: $data['name'] ?? null,
            status: isset($data['status']) ? PlanStatus::from($data['status']) : null,
        );
    }
}

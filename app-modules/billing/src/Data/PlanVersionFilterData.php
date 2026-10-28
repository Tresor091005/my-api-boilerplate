<?php

declare(strict_types=1);

namespace Lahatre\Billing\Data;

final readonly class PlanVersionFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: 'version',
            sortOrder: 'desc',
        );
    }
}

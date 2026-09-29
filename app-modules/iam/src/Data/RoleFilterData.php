<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class RoleFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 50),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'name',
            sortOrder: $data['sort_order'] ?? 'asc',
        );
    }
}

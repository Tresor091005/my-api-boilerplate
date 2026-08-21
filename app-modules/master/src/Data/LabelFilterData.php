<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

final readonly class LabelFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?string $value,
        public ?string $group,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'value',
            sortOrder: $data['sort_order'] ?? 'asc',
            value: $data['value'] ?? null,
            group: $data['group'] ?? null,
        );
    }
}

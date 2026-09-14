<?php

declare(strict_types=1);

namespace Lahatre\Library\Data;

final readonly class FolderFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?string $parentId,
        public ?string $search,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'name',
            sortOrder: $data['sort_order'] ?? 'asc',
            parentId: $data['parent_id'] ?? null,
            search: $data['search'] ?? null,
        );
    }
}

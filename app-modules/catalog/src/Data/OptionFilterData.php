<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

final readonly class OptionFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?string $name,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'name',
            sortOrder: $data['sort_order'] ?? 'asc',
            name: $data['name'] ?? null,
        );
    }
}

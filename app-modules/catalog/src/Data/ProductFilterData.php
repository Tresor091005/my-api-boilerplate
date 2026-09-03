<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Data;

final readonly class ProductFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?string $handle,
        public ?string $name,
        public ?string $description,
        public ?bool $hasActiveVariant,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: $data['sort_by'] ?? 'handle',
            sortOrder: $data['sort_order'] ?? 'asc',
            handle: $data['handle'] ?? null,
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            hasActiveVariant: array_key_exists('has_active_variant', $data) && $data['has_active_variant'] !== null
                ? (bool) $data['has_active_variant']
                : null,
        );
    }
}

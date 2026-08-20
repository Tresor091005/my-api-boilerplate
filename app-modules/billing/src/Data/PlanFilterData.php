<?php

declare(strict_types=1);

namespace Lahatre\Billing\Data;

final readonly class PlanFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?string $code,
        public ?string $name,
        public ?bool $isActive,
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
            isActive: array_key_exists('is_active', $data) && $data['is_active'] !== null
                ? (bool) $data['is_active']
                : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Iam\Data;

final readonly class InvitationFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?string $status,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 50),
            cursor: $data['cursor'] ?? null,
            sortBy: in_array($data['sort_by'] ?? null, ['email', 'created_at', 'updated_at'], true)
                ? $data['sort_by'] : 'created_at',
            sortOrder: ($data['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            status: $data['status'] ?? null,
        );
    }
}

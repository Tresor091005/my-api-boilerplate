<?php

declare(strict_types=1);

namespace Lahatre\Tax\Data;

use Lahatre\Tax\Enums\TaxKind;

final readonly class TaxFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?TaxKind $kind,
        public ?bool $isActive,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 20),
            cursor: $data['cursor'] ?? null,
            sortBy: (string) ($data['sort_by'] ?? 'code'),
            sortOrder: (string) ($data['sort_order'] ?? 'asc'),
            kind: isset($data['kind']) ? TaxKind::from((string) $data['kind']) : null,
            isActive: isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOL) : null,
        );
    }
}

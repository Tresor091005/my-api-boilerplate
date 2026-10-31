<?php

declare(strict_types=1);

namespace Lahatre\Billing\Data;

use Lahatre\Billing\Enums\CheckoutStatus;

final readonly class CheckoutFilterData
{
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public string $sortBy,
        public string $sortOrder,
        public ?CheckoutStatus $status,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            cursor: $data['cursor'] ?? null,
            sortBy: 'started_at',
            sortOrder: 'desc',
            status: isset($data['status']) ? CheckoutStatus::from($data['status']) : null,
        );
    }
}

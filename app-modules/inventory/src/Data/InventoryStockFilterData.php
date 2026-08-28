<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

use Carbon\CarbonImmutable;

final readonly class InventoryStockFilterData
{
    /**
     * @param  array<int, string>|null  $itemId
     * @param  array<int, string>|null  $locationId
     */
    private function __construct(
        public int $perPage,
        public ?string $cursor,
        public ?array $itemId,
        public ?array $locationId,
        public ?CarbonImmutable $expiringBefore,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 50),
            cursor: $data['cursor'] ?? null,
            itemId: $data['item_id'] ?? null,
            locationId: $data['location_id'] ?? null,
            expiringBefore: isset($data['expiring_before'])
                ? CarbonImmutable::createFromFormat('!Y-m-d', $data['expiring_before'])
                : (isset($data['expiring_within_days'])
                    ? CarbonImmutable::today()->addDays((int) $data['expiring_within_days'])
                    : null),
        );
    }
}

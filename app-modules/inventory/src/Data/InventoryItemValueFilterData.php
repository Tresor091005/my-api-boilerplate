<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

final readonly class InventoryItemValueFilterData
{
    /**
     * @param  array<int, string>|null  $locationId
     * @param  array<int, string>|null  $currencyCode
     */
    private function __construct(
        public ?array $locationId,
        public ?array $currencyCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            locationId: $data['location_id'] ?? null,
            currencyCode: $data['currency_code'] ?? null,
        );
    }
}

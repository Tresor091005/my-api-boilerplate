<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Data;

final readonly class InventoryLocationValueFilterData
{
    /**
     * @param  array<int, string>|null  $itemId
     * @param  array<int, string>|null  $currencyCode
     */
    private function __construct(
        public ?array $itemId,
        public ?array $currencyCode,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            itemId: $data['item_id'] ?? null,
            currencyCode: $data['currency_code'] ?? null,
        );
    }
}

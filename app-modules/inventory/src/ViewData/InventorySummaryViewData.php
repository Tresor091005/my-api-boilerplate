<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

readonly class InventorySummaryViewData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $itemId,
        public string $locationId,
        public ?string $sku,
        public int $remaining,
        public ?string $unitCode,
        public string $totalValue,
        public string $currencyCode,
    ) {}

    /**
     * @return array{item_id: string, location_id: string, sku: ?string, remaining: int, unit_code: ?string, total_value: string, currency_code: string}
     */
    public function toArray(): array
    {
        return [
            'item_id'       => $this->itemId,
            'location_id'   => $this->locationId,
            'sku'           => $this->sku,
            'remaining'     => $this->remaining,
            'unit_code'     => $this->unitCode,
            'total_value'   => $this->totalValue,
            'currency_code' => $this->currencyCode,
        ];
    }

    /**
     * @return array{item_id: string, location_id: string, sku: ?string, remaining: int, unit_code: ?string, total_value: string, currency_code: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

readonly class LocationStockItemViewData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $itemId,
        public ?string $sku,
        public int $remaining,
        public ?string $unitCode,
    ) {}

    /**
     * @return array{item_id: string, sku: ?string, remaining: int, unit_code: ?string}
     */
    public function toArray(): array
    {
        return [
            'item_id'   => $this->itemId,
            'sku'       => $this->sku,
            'remaining' => $this->remaining,
            'unit_code' => $this->unitCode,
        ];
    }

    /**
     * @return array{item_id: string, sku: ?string, remaining: int, unit_code: ?string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

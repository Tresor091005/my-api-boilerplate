<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

readonly class LocationStockViewData implements Arrayable, JsonSerializable
{
    /**
     * @param  Collection<int, LocationStockItemViewData>  $items
     */
    public function __construct(
        public string $locationId,
        public Collection $items,
    ) {}

    /**
     * @return array{location_id: string, items: array<int, array{item_id: string, sku: ?string, remaining: int, unit_code: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'location_id' => $this->locationId,
            'items'       => $this->items
                ->map(fn (LocationStockItemViewData $item): array => $item->toArray())
                ->all(),
        ];
    }

    /**
     * @return array{location_id: string, items: array<int, array{item_id: string, sku: ?string, remaining: int, unit_code: ?string}>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

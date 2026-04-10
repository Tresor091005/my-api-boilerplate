<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

readonly class ItemStockViewData implements Arrayable, JsonSerializable
{
    /**
     * @param  Collection<int, ItemStockLocationViewData>  $locations
     */
    public function __construct(
        public string $itemId,
        public int $totalRemaining,
        public string $unitCode,
        public Collection $locations,
    ) {}

    /**
     * @return array{item_id: string, total_remaining: int, unit_code: string, locations: array<int, array{location_id: string, remaining: int}>}
     */
    public function toArray(): array
    {
        return [
            'item_id'         => $this->itemId,
            'total_remaining' => $this->totalRemaining,
            'unit_code'       => $this->unitCode,
            'locations'       => $this->locations
                ->map(fn (ItemStockLocationViewData $location): array => $location->toArray())
                ->all(),
        ];
    }

    /**
     * @return array{item_id: string, total_remaining: int, unit_code: string, locations: array<int, array{location_id: string, remaining: int}>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

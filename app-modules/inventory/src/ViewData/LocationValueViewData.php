<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

readonly class LocationValueViewData implements Arrayable, JsonSerializable
{
    /**
     * @param  Collection<int, CurrencyValueViewData>  $totals
     * @param  Collection<int, LocationValueItemViewData>  $items
     */
    public function __construct(
        public string $locationId,
        public Collection $totals,
        public Collection $items,
    ) {}

    /**
     * @return array{
     *   location_id: string,
     *   totals: array<int, array{currency_code: string, total_value: string}>,
     *   items: array<int, array{item_id: string, values: array<int, array{currency_code: string, total_value: string}>}>
     * }
     */
    public function toArray(): array
    {
        return [
            'location_id' => $this->locationId,
            'totals'      => $this->totals
                ->map(fn (CurrencyValueViewData $value): array => $value->toArray())
                ->all(),
            'items'       => $this->items
                ->map(fn (LocationValueItemViewData $item): array => $item->toArray())
                ->all(),
        ];
    }

    /**
     * @return array{
     *   location_id: string,
     *   totals: array<int, array{currency_code: string, total_value: string}>,
     *   items: array<int, array{item_id: string, values: array<int, array{currency_code: string, total_value: string}>}>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}


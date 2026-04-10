<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

readonly class ItemValueViewData implements Arrayable, JsonSerializable
{
    /**
     * @param  Collection<int, CurrencyValueViewData>  $totals
     * @param  Collection<int, ItemValueLocationViewData>  $locations
     */
    public function __construct(
        public string $itemId,
        public Collection $totals,
        public Collection $locations,
    ) {}

    /**
     * @return array{
     *   item_id: string,
     *   totals: array<int, array{currency_code: string, total_value: string}>,
     *   locations: array<int, array{location_id: string, values: array<int, array{currency_code: string, total_value: string}>}>
     * }
     */
    public function toArray(): array
    {
        return [
            'item_id'    => $this->itemId,
            'totals'     => $this->totals
                ->map(fn (CurrencyValueViewData $value): array => $value->toArray())
                ->all(),
            'locations'  => $this->locations
                ->map(fn (ItemValueLocationViewData $location): array => $location->toArray())
                ->all(),
        ];
    }

    /**
     * @return array{
     *   item_id: string,
     *   totals: array<int, array{currency_code: string, total_value: string}>,
     *   locations: array<int, array{location_id: string, values: array<int, array{currency_code: string, total_value: string}>}>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

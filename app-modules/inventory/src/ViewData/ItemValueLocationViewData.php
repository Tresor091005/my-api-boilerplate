<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

readonly class ItemValueLocationViewData implements Arrayable, JsonSerializable
{
    /**
     * @param  Collection<int, CurrencyValueViewData>  $values
     */
    public function __construct(
        public string $locationId,
        public Collection $values,
    ) {}

    /**
     * @return array{location_id: string, values: array<int, array{currency_code: string, total_value: string}>}
     */
    public function toArray(): array
    {
        return [
            'location_id' => $this->locationId,
            'values'      => $this->values
                ->map(fn (CurrencyValueViewData $value): array => $value->toArray())
                ->all(),
        ];
    }

    /**
     * @return array{location_id: string, values: array<int, array{currency_code: string, total_value: string}>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

readonly class LocationValueItemViewData implements Arrayable, JsonSerializable
{
    /**
     * @param  Collection<int, CurrencyValueViewData>  $values
     */
    public function __construct(
        public string $itemId,
        public Collection $values,
    ) {}

    /**
     * @return array{item_id: string, values: array<int, array{currency_code: string, total_value: string}>}
     */
    public function toArray(): array
    {
        return [
            'item_id' => $this->itemId,
            'values'  => $this->values
                ->map(fn (CurrencyValueViewData $value): array => $value->toArray())
                ->all(),
        ];
    }

    /**
     * @return array{item_id: string, values: array<int, array{currency_code: string, total_value: string}>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}


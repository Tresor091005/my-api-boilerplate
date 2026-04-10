<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

readonly class CurrencyValueViewData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $currencyCode,
        public string $totalValue,
    ) {}

    /**
     * @return array{currency_code: string, total_value: string}
     */
    public function toArray(): array
    {
        return [
            'currency_code' => $this->currencyCode,
            'total_value'   => $this->totalValue,
        ];
    }

    /**
     * @return array{currency_code: string, total_value: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

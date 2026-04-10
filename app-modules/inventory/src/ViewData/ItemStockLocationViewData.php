<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

readonly class ItemStockLocationViewData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $locationId,
        public int $remaining,
    ) {}

    /**
     * @return array{location_id: string, remaining: int}
     */
    public function toArray(): array
    {
        return [
            'location_id' => $this->locationId,
            'remaining'   => $this->remaining,
        ];
    }

    /**
     * @return array{location_id: string, remaining: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

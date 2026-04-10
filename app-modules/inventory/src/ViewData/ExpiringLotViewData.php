<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

readonly class ExpiringLotViewData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $stockId,
        public string $itemId,
        public string $locationId,
        public int $remaining,
        public ?CarbonImmutable $expirationDate,
        public int $daysRemaining,
    ) {}

    /**
     * @return array{stock_id: string, item_id: string, location_id: string, remaining: int, expiration_date: ?CarbonImmutable, days_remaining: int}
     */
    public function toArray(): array
    {
        return [
            'stock_id'        => $this->stockId,
            'item_id'         => $this->itemId,
            'location_id'     => $this->locationId,
            'remaining'       => $this->remaining,
            'expiration_date' => $this->expirationDate,
            'days_remaining'  => $this->daysRemaining,
        ];
    }

    /**
     * @return array{stock_id: string, item_id: string, location_id: string, remaining: int, expiration_date: ?CarbonImmutable, days_remaining: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

readonly class AvailableLotViewData implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $stockId,
        public int $remaining,
        public int $quantity,
        public int $unitCost,
        public ?string $currencyCode,
        public ?CarbonImmutable $expirationDate,
        public ?CarbonImmutable $createdAt,
        public ?array $metadata,
    ) {}

    /**
     * @return array{stock_id: string, remaining: int, quantity: int, unit_cost: int, currency_code: ?string, expiration_date: ?CarbonImmutable, created_at: ?CarbonImmutable, metadata: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'stock_id'        => $this->stockId,
            'remaining'       => $this->remaining,
            'quantity'        => $this->quantity,
            'unit_cost'       => $this->unitCost,
            'currency_code'   => $this->currencyCode,
            'expiration_date' => $this->expirationDate,
            'created_at'      => $this->createdAt,
            'metadata'        => $this->metadata,
        ];
    }

    /**
     * @return array{stock_id: string, remaining: int, quantity: int, unit_cost: int, currency_code: ?string, expiration_date: ?CarbonImmutable, created_at: ?CarbonImmutable, metadata: array<string, mixed>|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

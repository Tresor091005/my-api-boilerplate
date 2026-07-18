<?php

declare(strict_types=1);

namespace Lahatre\Inventory\ViewData;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

readonly class ItemLocationLotsViewData implements Arrayable, JsonSerializable
{
    /**
     * @param  Collection<int, AvailableLotViewData>  $lots
     */
    public function __construct(
        public string $itemId,
        public string $locationId,
        public string $deductionStrategy,
        public int $totalRemaining,
        public string $unitCode,
        public Collection $lots,
    ) {}

    /**
     * @return array{item_id: string, location_id: string, deduction_strategy: string, total_remaining: int, unit_code: string, lots: array<int, array{stock_id: string, remaining: int, quantity: int, unit_cost: string|int, total_cost: string|int, cost_remainder: int, currency_code: ?string, expiration_date: ?CarbonImmutable, created_at: ?CarbonImmutable, metadata: array<string, mixed>|null}>}
     */
    public function toArray(): array
    {
        return [
            'item_id'            => $this->itemId,
            'location_id'        => $this->locationId,
            'deduction_strategy' => $this->deductionStrategy,
            'total_remaining'    => $this->totalRemaining,
            'unit_code'          => $this->unitCode,
            'lots'               => $this->lots
                ->map(fn (AvailableLotViewData $lot): array => $lot->toArray())
                ->all(),
        ];
    }

    /**
     * @return array{item_id: string, location_id: string, deduction_strategy: string, total_remaining: int, unit_code: string, lots: array<int, array{stock_id: string, remaining: int, quantity: int, unit_cost: string|int, total_cost: string|int, cost_remainder: int, currency_code: ?string, expiration_date: ?CarbonImmutable, created_at: ?CarbonImmutable, metadata: array<string, mixed>|null}>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

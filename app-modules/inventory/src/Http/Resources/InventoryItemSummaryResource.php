<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Models\InventoryItem;

/**
 * @mixin InventoryItem
 */
class InventoryItemSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'sku'                => $this->sku,
            'base_unit_code'     => $this->base_unit_code,
            'deduction_strategy' => $this->deduction_strategy,
            'is_active'          => $this->is_active,
            'total_remaining'    => $this->resolveTotalRemaining(),
            'active_lots_count'  => $this->resolveActiveLotsCount(),
            'locations'          => $this->whenLoaded('activeStockLocationSummaries', fn (): array => InventoryItemStockLocationSummaryResource::collection($this->activeStockLocationSummaries)->resolve()),
        ];
    }

    private function resolveTotalRemaining(): int
    {
        return $this->relationLoaded('activeStockLocationSummaries')
            ? (int) $this->activeStockLocationSummaries->sum('total_remaining')
            : ($this->relationLoaded('activeStocks') ? (int) $this->activeStocks->sum('remaining') : 0);
    }

    private function resolveActiveLotsCount(): int
    {
        return $this->relationLoaded('activeStockLocationSummaries')
            ? (int) $this->activeStockLocationSummaries->sum('active_lots_count')
            : ($this->relationLoaded('activeStocks') ? $this->activeStocks->count() : 0);
    }
}

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
        $totalRemaining = $this->resolveTotalRemaining();
        $activeLotsCount = $this->resolveActiveLotsCount();

        return [
            'id'                 => $this->id,
            'sku'                => $this->sku,
            'base_unit_code'     => $this->base_unit_code,
            'deduction_strategy' => $this->deduction_strategy,
            'is_active'          => $this->is_active,
            'total_remaining'    => $totalRemaining,
            'active_lots_count'  => $activeLotsCount,
            'active_stocks'      => InventoryAvailableLotResource::collection($this->whenLoaded('activeStocks')),
        ];
    }

    private function resolveTotalRemaining(): int
    {
        $attributes = $this->resource->getAttributes();

        if (array_key_exists('total_remaining', $attributes)) {
            return (int) ($attributes['total_remaining'] ?? 0);
        }

        if ($this->relationLoaded('activeStocks')) {
            return (int) $this->activeStocks->sum('remaining');
        }

        return 0;
    }

    private function resolveActiveLotsCount(): int
    {
        $attributes = $this->resource->getAttributes();

        if (array_key_exists('active_lots_count', $attributes)) {
            return (int) ($attributes['active_lots_count'] ?? 0);
        }

        if ($this->relationLoaded('activeStocks')) {
            return $this->activeStocks->count();
        }

        return 0;
    }
}

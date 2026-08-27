<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Models\InventoryItem;

/**
 * @mixin InventoryItem
 */
class InventoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'sku'                    => $this->sku,
            'base_unit_code'         => $this->base_unit_code,
            'deduction_strategy'     => $this->deduction_strategy,
            'is_expirable'           => $this->is_expirable,
            'stock_tracking_enabled' => $this->stock_tracking_enabled,
            'total_remaining'        => $this->resolveTotalRemaining(),
            'active_lots_count'      => $this->resolveActiveLotsCount(),
            'locations'              => $this->whenLoaded(
                'stockSummaries',
                fn (): array => $this->stockSummaries
                    ->map(fn ($summary): array => [
                        'location_id'       => data_get($summary, 'location_id'),
                        'total_remaining'   => (int) data_get($summary, 'total_remaining', 0),
                        'active_lots_count' => (int) data_get($summary, 'active_lots_count', 0),
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }

    private function resolveTotalRemaining(): int
    {
        return $this->relationLoaded('stockSummaries')
            ? (int) $this->stockSummaries->sum('total_remaining')
            : 0;
    }

    private function resolveActiveLotsCount(): int
    {
        return $this->relationLoaded('stockSummaries')
            ? (int) $this->stockSummaries->sum('active_lots_count')
            : 0;
    }
}

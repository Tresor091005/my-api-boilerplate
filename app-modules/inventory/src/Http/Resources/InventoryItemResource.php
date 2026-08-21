<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Shared\Http\Resources\Concerns\RendersResponseIncludes;

/**
 * @mixin InventoryItem
 */
class InventoryItemResource extends JsonResource
{
    use RendersResponseIncludes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'itemable_type'          => $this->itemable_type,
            'itemable_id'            => $this->itemable_id,
            'sku'                    => $this->sku,
            'base_unit_code'         => $this->base_unit_code,
            'deduction_strategy'     => $this->deduction_strategy,
            'is_expirable'           => $this->is_expirable,
            'stock_tracking_enabled' => $this->stock_tracking_enabled,
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
            'itemable'               => $this->includeWhenRequestedAndLoaded(
                include: 'itemable',
                relation: 'itemable',
                resolver: function (): ?array {
                    if ($this->itemable instanceof HasInventoryItem) {
                        return $this->itemable->toInventoryItemSummary();
                    }

                    return null;
                },
            ),
            'stocks' => $this->includeWhenRequestedAndLoaded(
                include: 'stocks',
                relation: 'stocks',
                resolver: fn ($stocks): mixed => InventoryStockResource::collection($stocks),
            ),
            'movements' => $this->includeWhenRequestedAndLoaded(
                include: 'movements',
                relation: 'movements',
                resolver: fn ($movements): mixed => InventoryMovementResource::collection($movements),
            ),
        ];
    }
}

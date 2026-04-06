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
            'id'                 => $this->id,
            'itemable_type'      => $this->itemable_type,
            'itemable_id'        => $this->itemable_id,
            'sku'                => $this->sku,
            'base_unit_code'     => $this->base_unit_code,
            'deduction_strategy' => $this->deduction_strategy,
            'is_active'          => $this->is_active,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
            'stocks'             => InventoryStockResource::collection($this->whenLoaded('stocks')),
            'movements'          => InventoryMovementResource::collection($this->whenLoaded('movements')),
        ];
    }
}

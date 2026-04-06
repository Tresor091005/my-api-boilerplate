<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Models\InventoryLocation;

/**
 * @mixin InventoryLocation
 */
class InventoryLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'external_type' => $this->external_type,
            'external_id'   => $this->external_id,
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'stocks'        => InventoryStockResource::collection($this->whenLoaded('stocks')),
            'movements'     => InventoryMovementResource::collection($this->whenLoaded('movements')),
        ];
    }
}

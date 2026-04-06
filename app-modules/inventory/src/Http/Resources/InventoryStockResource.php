<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Http\Resources\CurrencyResource;
use Lahatre\Master\Http\Resources\UnitResource;

/**
 * @mixin InventoryStock
 */
class InventoryStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'item_id'         => $this->item_id,
            'location_id'     => $this->location_id,
            'unit_cost'       => $this->unit_cost,
            'currency_code'   => $this->currency_code,
            'quantity'        => $this->quantity,
            'remaining'       => $this->remaining,
            'unit_code'       => $this->unit_code,
            'expiration_date' => $this->expiration_date,
            'metadata'        => $this->metadata,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
            'location'        => InventoryLocationResource::make($this->whenLoaded('location')),
            'unit'            => UnitResource::make($this->whenLoaded('unit')),
            'currency'        => CurrencyResource::make($this->whenLoaded('currency')),
            'movements'       => InventoryMovementResource::collection($this->whenLoaded('movements')),
        ];
    }
}

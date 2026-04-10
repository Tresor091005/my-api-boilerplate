<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Http\Resources\CurrencyResource;
use Lahatre\Master\Http\Resources\UnitResource;

/**
 * @mixin InventoryMovement
 */
class InventoryMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'movement_type'   => $this->movement_type,
            'transaction_id'  => $this->transaction_id,
            'item_id'         => $this->item_id,
            'stock_id'        => $this->stock_id,
            'location_id'     => $this->location_id,
            'quantity'        => $this->quantity,
            'unit_code'       => $this->unit_code,
            'unit_cost'       => $this->resolveUnitCost(),
            'currency_code'   => $this->currency_code,
            'expiration_date' => $this->expiration_date,
            'metadata'        => $this->metadata,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
            'location'        => InventoryLocationResource::make($this->whenLoaded('location')),
            'stock'           => InventoryStockResource::make($this->whenLoaded('stock')),
            'unit'            => UnitResource::make($this->whenLoaded('unit')),
            'currency'        => CurrencyResource::make($this->whenLoaded('currency')),
        ];
    }

    private function resolveUnitCost(): string|int
    {
        if (! $this->currency_code) {
            return $this->unit_cost;
        }

        return app(MasterInterface::class)->fromMinor((string) $this->unit_cost, $this->currency_code);
    }
}

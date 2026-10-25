<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Http\Resources\Fields\InventoryValues;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Contracts\MasterInterface;

/**
 * A stock lot inside the item location lots answer, which is an array and stays outside response
 * selection. InventoryStockResource renders stocks everywhere else.
 *
 * @mixin InventoryStock
 */
class InventoryLotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $master = app(MasterInterface::class);

        return [
            'id'                => $this->id,
            'item_id'           => $this->item_id,
            'location_id'       => $this->location_id,
            'unit_cost'         => InventoryValues::amount($master, $this->resource, InventoryValues::stockMinorCost($this->resource, 'unit_cost')),
            'total_cost'        => InventoryValues::amount($master, $this->resource, InventoryValues::stockMinorCost($this->resource, 'total_cost')),
            'cost_remainder'    => InventoryValues::amount($master, $this->resource, InventoryValues::stockMinorCost($this->resource, 'cost_remainder')),
            'currency_code'     => $this->currency_code,
            'quantity'          => $this->quantity,
            'remaining'         => $this->remaining,
            'base_unit_code'    => $this->base_unit_code,
            'expiration_date'   => InventoryValues::expirationDate($this->resource),
            'days_remaining'    => InventoryValues::daysRemaining($this->resource),
            'metadata'          => $this->metadata,
            'exchange_metadata' => $this->exchange_metadata,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}

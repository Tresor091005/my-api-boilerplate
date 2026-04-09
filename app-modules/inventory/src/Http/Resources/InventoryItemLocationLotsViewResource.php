<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\ViewData\ItemLocationLotsViewData;

/**
 * @mixin ItemLocationLotsViewData
 */
class InventoryItemLocationLotsViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'item_id'            => $this->itemId,
            'location_id'        => $this->locationId,
            'deduction_strategy' => $this->deductionStrategy,
            'total_remaining'    => $this->totalRemaining,
            'unit_code'          => $this->unitCode,
            'lots'               => InventoryAvailableLotResource::collection($this->lots),
        ];
    }
}

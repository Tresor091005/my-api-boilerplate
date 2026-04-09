<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\ViewData\ItemStockViewData;

/**
 * @mixin ItemStockViewData
 */
class InventoryItemStockViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'item_id'         => $this->itemId,
            'total_remaining' => $this->totalRemaining,
            'unit_code'       => $this->unitCode,
            'locations'       => InventoryItemStockLocationViewResource::collection($this->locations),
        ];
    }
}

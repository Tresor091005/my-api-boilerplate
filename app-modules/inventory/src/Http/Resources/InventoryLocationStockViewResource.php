<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\ViewData\LocationStockViewData;

/**
 * @mixin LocationStockViewData
 */
class InventoryLocationStockViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'location_id' => $this->locationId,
            'items'       => InventoryLocationStockItemViewResource::collection($this->items),
        ];
    }
}

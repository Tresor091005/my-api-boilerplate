<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\ViewData\LocationStockItemViewData;

/**
 * @mixin LocationStockItemViewData
 */
class InventoryLocationStockItemViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'item_id'   => $this->itemId,
            'sku'       => $this->sku,
            'remaining' => $this->remaining,
            'unit_code' => $this->unitCode,
        ];
    }
}

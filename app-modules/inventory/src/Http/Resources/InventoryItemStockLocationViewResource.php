<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\ViewData\ItemStockLocationViewData;

/**
 * @mixin ItemStockLocationViewData
 */
class InventoryItemStockLocationViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'location_id' => $this->locationId,
            'remaining'   => $this->remaining,
        ];
    }
}

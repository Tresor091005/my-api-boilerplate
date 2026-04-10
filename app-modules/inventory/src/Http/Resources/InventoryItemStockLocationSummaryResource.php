<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemStockLocationSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'location_id'       => data_get($this->resource, 'location_id'),
            'total_remaining'   => (int) data_get($this->resource, 'total_remaining', 0),
            'active_lots_count' => (int) data_get($this->resource, 'active_lots_count', 0),
        ];
    }
}

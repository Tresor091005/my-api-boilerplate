<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventorySummaryRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'item_id'     => data_get($this->resource, 'item_id'),
            'location_id' => data_get($this->resource, 'location_id'),
            'sku'         => data_get($this->resource, 'sku'),
            'remaining'   => (int) data_get($this->resource, 'remaining', 0),
            'unit_code'   => data_get($this->resource, 'unit_code'),
        ];
    }
}

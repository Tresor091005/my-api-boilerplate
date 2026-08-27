<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Lahatre\Inventory\Models\InventoryStock;

class InventoryItemLocationLotsResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, InventoryStock> $lots */
        $lots = $this->resource['lots'];

        return [
            'item_id'            => $this->resource['item_id'],
            'location_id'        => $this->resource['location_id'],
            'deduction_strategy' => $this->resource['deduction_strategy'],
            'total_remaining'    => $this->resource['total_remaining'],
            'unit_code'          => $this->resource['unit_code'],
            'lots'               => InventoryStockResource::collection($lots)->resolve($request),
        ];
    }
}

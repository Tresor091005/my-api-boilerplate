<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryExpiringStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $expirationDate = data_get($this->resource, 'expiration_date');
        $daysRemaining = $expirationDate instanceof CarbonInterface
            ? (int) now()->startOfDay()->diffInDays($expirationDate->startOfDay(), false)
            : 0;

        return [
            'stock_id'        => data_get($this->resource, 'id'),
            'item_id'         => data_get($this->resource, 'item_id'),
            'location_id'     => data_get($this->resource, 'location_id'),
            'remaining'       => (int) data_get($this->resource, 'remaining', 0),
            'expiration_date' => $expirationDate,
            'days_remaining'  => $daysRemaining,
        ];
    }
}

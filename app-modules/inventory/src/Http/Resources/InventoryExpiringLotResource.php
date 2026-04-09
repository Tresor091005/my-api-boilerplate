<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\ViewData\ExpiringLotViewData;

/**
 * @mixin ExpiringLotViewData
 */
class InventoryExpiringLotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stock_id'        => $this->stockId,
            'item_id'         => $this->itemId,
            'location_id'     => $this->locationId,
            'remaining'       => $this->remaining,
            'expiration_date' => $this->expirationDate,
            'days_remaining'  => $this->daysRemaining,
        ];
    }
}

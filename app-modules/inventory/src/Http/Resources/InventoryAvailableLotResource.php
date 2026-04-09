<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAvailableLotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stock_id'        => data_get($this->resource, 'stockId', data_get($this->resource, 'id')),
            'remaining'       => (int) data_get($this->resource, 'remaining', 0),
            'quantity'        => (int) data_get($this->resource, 'quantity', 0),
            'unit_cost'       => (int) data_get($this->resource, 'unitCost', data_get($this->resource, 'unit_cost', 0)),
            'currency_code'   => data_get($this->resource, 'currencyCode', data_get($this->resource, 'currency_code')),
            'expiration_date' => data_get($this->resource, 'expirationDate', data_get($this->resource, 'expiration_date')),
            'created_at'      => data_get($this->resource, 'createdAt', data_get($this->resource, 'created_at')),
            'metadata'        => data_get($this->resource, 'metadata'),
        ];
    }
}

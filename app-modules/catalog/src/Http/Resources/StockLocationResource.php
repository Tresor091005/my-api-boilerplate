<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Master\Http\Resources\AddressResource;

/** @mixin StockLocation */
class StockLocationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'handle'     => $this->handle,
            'name'       => $this->name,
            'is_active'  => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'address'    => $this->whenLoaded(
                'address',
                fn (): AddressResource => AddressResource::make($this->address),
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Models\Price;
use Lahatre\Master\Http\Resources\CurrencyResource;

/**
 * @mixin Price
 */
class PriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'currency_code' => $this->currency_code,
            'min_quantity'  => $this->min_quantity,
            'max_quantity'  => $this->max_quantity,
            'step'          => $this->step,
            'amount'        => fromMinor((string) $this->amount, $this->currency_code),
            'is_active'     => $this->is_active,
            'active_from'   => $this->active_from,
            'active_to'     => $this->active_to,
            'currency'      => CurrencyResource::make($this->whenLoaded('currency')),
        ];
    }
}

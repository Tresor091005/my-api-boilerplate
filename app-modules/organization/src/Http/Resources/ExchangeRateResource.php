<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Organization\Models\ExchangeRate;

/**
 * @mixin ExchangeRate
 */
class ExchangeRateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'source_currency_code' => $this->source_currency_code,
            'target_currency_code' => $this->target_currency_code,
            'context'              => $this->context->value,
            'rate'                 => $this->rate,
            'effective_at'         => $this->effective_at,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}

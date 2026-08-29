<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Master\Models\Address;

/** @mixin Address */
class AddressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'line'       => $this->line,
            'city'       => $this->city,
            'country'    => $this->country,
            'is_primary' => $this->is_primary,
        ];
    }
}

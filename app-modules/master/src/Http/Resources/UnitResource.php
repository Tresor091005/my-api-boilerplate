<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Master\Models\Unit;

/**
 * @mixin Unit
 */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'code'   => $this->code,
            'name'   => $this->name,
            'ratio'  => $this->ratio,
            'symbol' => $this->symbol,
            'group'  => UnitGroupResource::make($this->whenLoaded('group')),
        ];
    }
}

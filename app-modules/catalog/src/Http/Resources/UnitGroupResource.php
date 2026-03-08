<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Models\UnitGroup;

/**
 * @mixin UnitGroup
 */
class UnitGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'is_builtin' => $this->is_builtin,
            'units'      => UnitResource::collection($this->whenLoaded('units')),
        ];
    }
}

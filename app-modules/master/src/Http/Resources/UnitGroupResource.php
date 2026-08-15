<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Shared\Http\Resources\Concerns\RendersResponseIncludes;

/**
 * @mixin UnitGroup
 */
class UnitGroupResource extends JsonResource
{
    use RendersResponseIncludes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'is_builtin' => $this->is_builtin,
            'units'      => $this->includeWhenRequestedAndLoaded(
                include: 'units',
                relation: 'units',
                resolver: fn ($units): mixed => UnitResource::collection($units),
            ),
        ];
    }
}

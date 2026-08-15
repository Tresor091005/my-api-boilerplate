<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Http\Resources\Concerns\RendersResponseIncludes;

/**
 * @mixin Unit
 */
class UnitResource extends JsonResource
{
    use RendersResponseIncludes;

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
            'group'  => $this->includeWhenRequestedAndLoaded(
                include: ['group', 'units.group'],
                relation: 'group',
                resolver: fn ($group): mixed => UnitGroupResource::make($group),
            ),
        ];
    }
}

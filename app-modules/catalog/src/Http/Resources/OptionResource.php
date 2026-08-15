<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Models\Option;
use Lahatre\Shared\Http\Resources\Concerns\RendersResponseIncludes;

/**
 * @mixin Option
 */
class OptionResource extends JsonResource
{
    use RendersResponseIncludes;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'values'     => $this->includeWhenRequestedAndLoaded(
                include: 'values',
                relation: 'values',
                resolver: fn ($values): mixed => OptionValueResource::collection($values),
            ),
        ];
    }
}

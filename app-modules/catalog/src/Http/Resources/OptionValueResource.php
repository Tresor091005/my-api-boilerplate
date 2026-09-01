<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Models\OptionValue;

/**
 * @mixin OptionValue
 */
class OptionValueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'option_id'  => $this->option_id,
            'value'      => $this->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'option'     => $this->whenLoaded(
                'option',
                fn ($option): mixed => OptionResource::make($option),
            ),
        ];
    }

    // TODO lite version is needed for this and option too
}

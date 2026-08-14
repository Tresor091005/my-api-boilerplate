<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Master\Models\Tag;

/** @mixin Tag */
class TagResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'type'       => $this->type,
            'order_col'  => $this->order_col,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

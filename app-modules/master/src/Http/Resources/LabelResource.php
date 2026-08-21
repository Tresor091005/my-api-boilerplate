<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Master\Models\Label;

/** @mixin Label */
class LabelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'value'      => $this->value,
            'slug'       => $this->slug,
            'group'      => $this->group,
            'order_col'  => $this->order_col,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

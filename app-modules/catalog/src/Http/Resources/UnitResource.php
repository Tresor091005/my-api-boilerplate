<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Models\Unit;

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
            'id'         => $this->id,
            'code'       => $this->code,
            'name'       => $this->name,
            'ratio'      => $this->ratio,
            'symbol'     => $this->symbol,
            'unit_group' => $this->unit_group,
            'is_builtin' => $this->is_builtin,
            'is_active'  => $this->is_active,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Shared\Http\Resources\Concerns\RendersResponseIncludes;

/**
 * @mixin InventoryLocation
 */
class InventoryLocationResource extends JsonResource
{
    use RendersResponseIncludes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'external_type' => $this->external_type,
            'external_id'   => $this->external_id,
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'external'      => $this->includeWhenRequestedAndLoaded(
                include: 'external',
                relation: 'external',
                resolver: function (): ?array {
                    if ($this->external instanceof HasInventoryLocation) {
                        return $this->external->toInventoryLocationSummary();
                    }

                    return null;
                },
            ),
            'stocks' => $this->includeWhenRequestedAndLoaded(
                include: 'stocks',
                relation: 'stocks',
                resolver: fn ($stocks): mixed => InventoryStockResource::collection($stocks),
            ),
            'movements' => $this->includeWhenRequestedAndLoaded(
                include: 'movements',
                relation: 'movements',
                resolver: fn ($movements): mixed => InventoryMovementResource::collection($movements),
            ),
        ];
    }
}

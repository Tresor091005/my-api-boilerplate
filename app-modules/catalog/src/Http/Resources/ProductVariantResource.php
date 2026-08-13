<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Inventory\Http\Resources\InventoryItemSummaryResource;
use Lahatre\Master\Http\Resources\UnitGroupResource;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'product_id'    => $this->product_id,
            'sku'           => $this->sku,
            'unit_group_id' => $this->unit_group_id,
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'options'       => $this->optionValues->values()->mapWithKeys(fn ($optionValue, $index): array => [$optionValue->option->name => $optionValue->value]),
            'unit_group'    => UnitGroupResource::make($this->whenLoaded('unitGroup')),
            'inventory'     => $this->whenLoaded(
                'inventoryItem',
                fn (): array => InventoryItemSummaryResource::make($this->inventoryItem)->resolve($request)
            ),
        ];
    }
}

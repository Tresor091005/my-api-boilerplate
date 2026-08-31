<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Inventory\Http\Resources\InventoryItemResource;
use Lahatre\Master\Http\Resources\LabelResource;
use Lahatre\Master\Http\Resources\UnitGroupResource;
use Lahatre\Shared\Http\Resources\Concerns\RendersResponseIncludes;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    use RendersResponseIncludes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'product_id'    => $this->product_id,
            'sku'           => $this->catalogItem->sku,
            'unit_group_id' => $this->catalogItem->unit_group_id,
            'is_active'     => $this->catalogItem->is_active,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'options'       => $this->whenLoaded('optionValues', function ($optionValues): mixed {
                if ($optionValues->contains(
                    fn ($optionValue): bool => !$optionValue->relationLoaded('option')
                )) {
                    return new MissingValue;
                }

                return $optionValues->values()->mapWithKeys(
                    fn ($optionValue): array => [$optionValue->option->name => $optionValue->value]
                );
            }),
            'unit_group' => $this->includeWhenRequestedAndLoaded(
                include: 'unit_group',
                relation: 'catalogItem',
                resolver: function ($catalogItem): mixed {
                    if ($catalogItem === null || !$catalogItem->relationLoaded('unitGroup')) {
                        return new MissingValue;
                    }

                    return UnitGroupResource::make($catalogItem->unitGroup);
                },
            ),
            'labels' => $this->includeWhenRequestedAndLoaded(
                include: 'labels',
                relation: 'labels',
                resolver: fn ($labels) => LabelResource::collection($labels),
            ),
            'inventory' => $this->includeWhenRequestedAndLoaded(
                include: 'inventory',
                relation: 'catalogItem',
                resolver: function ($catalogItem): mixed {
                    if ($catalogItem === null || !$catalogItem->relationLoaded('inventoryItem')) {
                        return new MissingValue;
                    }

                    return InventoryItemResource::make($catalogItem->inventoryItem);
                },
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Models\ProductVariant;

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
            'options_label' => $this->options_label,
            'product_id'    => $this->product_id,
            'sku'           => $this->sku,
            'unit_group_id' => $this->unit_group_id,
            'manage_stock'  => $this->manage_stock,
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'options'       => $this->optionValues->values()->mapWithKeys(fn ($optionValue, $index): array => [$optionValue->option->name => $optionValue->value]),
            'unit_group'    => UnitGroupResource::make($this->whenLoaded('unitGroup')),
            'prices'        => PriceResource::collection($this->whenLoaded('prices')),
        ];
    }
}

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
            'unit_code'     => $this->unit_code,
            'min_quantity'  => $this->min_quantity,
            'max_quantity'  => $this->max_quantity,
            'step'          => $this->step,
            'is_default'    => $this->is_default,
            'is_stockable'  => $this->is_stockable,
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
            'options' => $this->optionValues->values()->mapWithKeys(function ($optionValue, $index) {
                return [$optionValue->option->name => $optionValue->value];
            }),
            'unit'          => UnitResource::make($this->whenLoaded('unit')),
            'prices'        => PriceResource::collection($this->whenLoaded('prices')),
        ];
    }
}

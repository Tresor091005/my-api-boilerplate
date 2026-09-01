<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Lahatre\Catalog\Models\Product;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'handle'      => $this->handle,
            'name'        => $this->name,
            'description' => $this->description,
            'is_active'   => $this->is_active,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'options'     => $this->whenLoaded(
                'optionValues',
                function ($optionValues): mixed {
                    if ($optionValues->contains(
                        fn ($optionValue): bool => !$optionValue->relationLoaded('option')
                    )) {
                        return new MissingValue;
                    }

                    return $optionValues
                        ->groupBy('option_id')
                        ->map(fn ($values): array => [
                            'name'   => $values->first()->option->name,
                            'values' => $values->pluck('value')->unique()->values()->all(),
                        ])
                        ->values();
                },
            ),
            'variants' => $this->whenLoaded(
                'variants',
                fn ($variants): mixed => ProductVariantResource::collection($variants),
            ),
            'categories' => $this->whenLoaded(
                'categories',
                fn ($categories): mixed => CategoryResource::collection($categories),
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Lahatre\Catalog\Http\Resources\Concerns\RendersCatalogItem;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Master\Http\Resources\LabelResource;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    use RendersCatalogItem;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'product_id' => $this->product_id,
            ...$this->catalogItemFields(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'options'    => $this->whenLoaded('optionValues', function ($optionValues): mixed {
                if ($optionValues->contains(
                    fn ($optionValue): bool => !$optionValue->relationLoaded('option')
                )) {
                    return new MissingValue;
                }

                return $optionValues->values()->mapWithKeys(
                    fn ($optionValue): array => [$optionValue->option->name => $optionValue->value]
                );
            }),
            'labels' => $this->includeWhenRequestedAndLoaded(
                include: 'labels',
                relation: 'labels',
                resolver: fn ($labels) => LabelResource::collection($labels),
            ),
            ...$this->catalogItemRelations(),
        ];
    }
}

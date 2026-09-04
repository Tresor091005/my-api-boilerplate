<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\StockTransferLine;

/** @mixin StockTransferLine */
class StockTransferLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'catalog_item_type' => $this->catalog_item_type,
            'catalog_item_id'   => $this->catalog_item_id,
            'position'          => $this->position,
            'quantity'          => $this->quantity,
            'display_unit_code' => $this->display_unit_code,
            'strategy'          => $this->strategy,
            'stock_ids'         => $this->stock_ids,
            'item'              => $this->whenLoaded(
                'item',
                fn (mixed $item): JsonResource|MissingValue => $this->itemResource($item),
            ),
        ];
    }

    protected function itemResource(mixed $item): JsonResource|MissingValue
    {
        if (!$item instanceof Model) {
            return new MissingValue;
        }

        return match ($item::class) {
            ProductVariant::class => ProductVariantResource::make($item),
            Bundle::class         => BundleResource::make($item),
            default               => new MissingValue,
        };
    }
}

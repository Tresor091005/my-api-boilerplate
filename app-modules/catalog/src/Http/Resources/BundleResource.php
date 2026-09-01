<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Http\Resources\Concerns\RendersCatalogItem;
use Lahatre\Catalog\Models\Bundle;

/** @mixin Bundle */
class BundleResource extends JsonResource
{
    use RendersCatalogItem;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'handle' => $this->handle,
            'name'   => $this->name,
            ...$this->catalogItemFields(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'items'      => $this->whenLoaded(
                'items',
                fn ($items): JsonResource => BundleItemResource::collection($items),
            ),
            ...$this->catalogItemRelations(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources\Concerns;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Inventory\Http\Resources\InventoryItemResource;
use Lahatre\Master\Http\Resources\UnitGroupResource;

/** @mixin JsonResource */
trait RendersCatalogItem
{
    /**
     * @return array{sku: mixed, unit_group_id: mixed, is_active: mixed}
     */
    protected function catalogItemFields(): array
    {
        return [
            'sku'           => $this->whenLoaded('catalogItem', fn (CatalogItem $catalogItem): string => $catalogItem->sku),
            'unit_group_id' => $this->whenLoaded('catalogItem', fn (CatalogItem $catalogItem): string => $catalogItem->unit_group_id),
            'is_active'     => $this->whenLoaded('catalogItem', fn (CatalogItem $catalogItem): bool => $catalogItem->is_active),
        ];
    }

    /**
     * @return array{unit_group: mixed, inventory: mixed}
     */
    protected function catalogItemRelations(): array
    {
        return [
            'unit_group' => $this->whenLoaded('catalogItem', function (?CatalogItem $catalogItem): mixed {
                if ($catalogItem === null || !$catalogItem->relationLoaded('unitGroup')) {
                    return new MissingValue;
                }

                return UnitGroupResource::make($catalogItem->unitGroup);
            }),
            'inventory' => $this->whenLoaded('catalogItem', function (?CatalogItem $catalogItem): mixed {
                if ($catalogItem === null || !$catalogItem->relationLoaded('inventoryItem')) {
                    return new MissingValue;
                }

                return InventoryItemResource::make($catalogItem->inventoryItem);
            }),
        ];
    }
}

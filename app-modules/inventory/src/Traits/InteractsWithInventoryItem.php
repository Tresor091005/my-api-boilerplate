<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Models\InventoryItem;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/**
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements HasInventoryItem
 *
 * @mixin Model
 */
trait InteractsWithInventoryItem
{
    use HasRelationships;

    /**
     * Get the model's inventory item.
     *
     * @return MorphOne<InventoryItem, Model>
     */
    public function inventoryItem(): MorphOne
    {
        /** @var MorphOne<InventoryItem, Model> $relation */
        $relation = $this->morphOne(InventoryItem::class, 'itemable');

        return $relation;
    }

    /**
     * Get all the model's inventory stocks through its inventory item.
     */
    public function inventoryItemStocks(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations($this->inventoryItem(), (new InventoryItem())->stocks());
    }

    /**
     * Get all active inventory stocks through the model's inventory item.
     */
    public function activeInventoryItemStocks(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations($this->inventoryItem(), (new InventoryItem())->activeStocks());
    }

    /**
     * Aggregated active stocks grouped by location (lightweight summary).
     */
    public function inventoryItemStockSummaries(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations($this->inventoryItem(), (new InventoryItem())->stockSummaries());
    }

    /**
     * Get all the model's inventory movements through its inventory item.
     */
    public function inventoryItemMovements(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations($this->inventoryItem(), (new InventoryItem())->movements());
    }
}

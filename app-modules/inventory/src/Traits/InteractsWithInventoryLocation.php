<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Models\InventoryLocation;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/**
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements HasInventoryLocation
 *
 * @mixin Model
 */
trait InteractsWithInventoryLocation
{
    use HasRelationships;

    /**
     * Get the model's inventory location.
     */
    public function inventoryLocation(): MorphOne
    {
        return $this->morphOne(InventoryLocation::class, 'external');
    }

    /**
     * Get all the model's inventory stocks through its inventory location.
     */
    public function locationStocks(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations($this->inventoryLocation(), (new InventoryLocation())->stocks());
    }

    /**
     * Get all active inventory stocks through its inventory location.
     */
    public function activeLocationStocks(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations($this->inventoryLocation(), (new InventoryLocation())->activeStocks());
    }

    /**
     * Get all the model's inventory movements through its inventory location.
     */
    public function locationMovements(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations($this->inventoryLocation(), (new InventoryLocation())->movements());
    }
}

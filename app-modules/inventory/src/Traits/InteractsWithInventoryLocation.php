<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
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
     *
     * @return MorphOne<InventoryLocation, Model>
     */
    public function inventoryLocation(): MorphOne
    {
        $organizationId = currentOrganizationId();

        /** @var MorphOne<InventoryLocation, Model> $relation */
        $relation = $this->morphOne(InventoryLocation::class, 'external')
            ->where('inventory_locations.organization_id', $organizationId);

        return $relation;
    }

    /**
     * Get all the model's inventory stocks through its inventory location.
     */
    public function inventoryLocationStocks(): HasManyDeep
    {
        return $this->hasManyDeepFromRelationsWithConstraints([$this, 'inventoryLocation'], [new InventoryLocation, 'stocks']);
    }

    /**
     * Get all active inventory stocks through its inventory location.
     */
    public function activeInventoryLocationStocks(): HasManyDeep
    {
        return $this->hasManyDeepFromRelationsWithConstraints([$this, 'inventoryLocation'], [new InventoryLocation, 'activeStocks']);
    }

    /**
     * Get aggregated active stocks grouped by item through its inventory location.
     */
    public function inventoryLocationStockSummaries(): HasManyDeep
    {
        return $this->hasManyDeepFromRelationsWithConstraints([$this, 'inventoryLocation'], [new InventoryLocation, 'stockSummaries'])
            ->select([
                'inventory_stocks.location_id',
                'inventory_stocks.item_id',
                DB::raw('SUM(inventory_stocks.remaining) as total_remaining'),
                DB::raw('COUNT(*) as active_lots_count'),
            ])
            ->groupBy(
                'inventory_stocks.location_id',
                'inventory_stocks.item_id',
                'inventory_locations.external_id',
            )
            ->orderBy('inventory_stocks.item_id');
    }

    /**
     * Get all the model's inventory movements through its inventory location.
     */
    public function inventoryLocationMovements(): HasManyDeep
    {
        return $this->hasManyDeepFromRelationsWithConstraints([$this, 'inventoryLocation'], [new InventoryLocation, 'movements']);
    }
}

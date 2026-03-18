<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryTransaction;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

trait InteractsWithInventory
{
    use HasRelationships;

    /**
     * Get the model's inventory item.
     */
    public function inventoryItem(): MorphOne
    {
        return $this->morphOne(InventoryItem::class, 'itemable');
    }

    /**
     * Get all the model's inventory stocks through its inventory item.
     */
    public function stocks(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations($this->inventoryItem(), (new InventoryItem())->stocks());
    }

    /**
     * Get all the model's inventory movements through its inventory item.
     */
    public function movements(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations($this->inventoryItem(), (new InventoryItem())->movements());
    }

    /**
     * Get the current stock for the item, optionally filtered by location.
     */
    public function getStock(?HasInventoryLocation $location = null): float
    {
        $query = $this->stocks()->where('remaining', '>', 0);

        if ($location) {
            $inventoryLocation = app(InventoryInterface::class)->createLocation($location);
            $query->where('location_id', $inventoryLocation->id);
        }

        return (float) $query->sum('remaining');
    }

    /**
     * Record an inventory 'in' transaction.
     */
    public function recordInventoryIn(
        HasInventoryLocation $location,
        float $quantity,
        string $unitCode,
        ?int $unitCost = null,
        ?string $currencyCode = null,
        array $metadata = []
    ): InventoryTransaction {
        $inventoryItem = app(InventoryInterface::class)->createItem($this);
        $inventoryLocation = app(InventoryInterface::class)->createLocation($location);

        return app(InventoryInterface::class)->recordTransaction([
            'reference_type'   => $this->getMorphClass(),
            'reference_id'     => (string) $this->getKey(),
            'transaction_type' => 'in',
            'movements'        => [
                [
                    'item_id'       => $inventoryItem->id,
                    'location_id'   => $inventoryLocation->id,
                    'type'          => 'in',
                    'quantity'      => $quantity,
                    'unit_code'     => $unitCode,
                    'unit_cost'     => $unitCost,
                    'currency_code' => $currencyCode,
                    'metadata'      => $metadata,
                ],
            ],
        ]);
    }

    /**
     * Record an inventory 'out' transaction.
     */
    public function recordInventoryOut(
        HasInventoryLocation $location,
        float $quantity,
        string $unitCode,
        array $metadata = []
    ): InventoryTransaction {
        $inventoryItem = app(InventoryInterface::class)->createItem($this);
        $inventoryLocation = app(InventoryInterface::class)->createLocation($location);

        return app(InventoryInterface::class)->recordTransaction([
            'reference_type'   => $this->getMorphClass(),
            'reference_id'     => (string) $this->getKey(),
            'transaction_type' => 'out',
            'movements'        => [
                [
                    'item_id'     => $inventoryItem->id,
                    'location_id' => $inventoryLocation->id,
                    'type'        => 'out',
                    'quantity'    => $quantity,
                    'unit_code'   => $unitCode,
                    'metadata'    => $metadata,
                ],
            ],
        ]);
    }

    /**
     * Record an inventory adjustment.
     */
    public function recordInventoryAdjustment(
        HasInventoryLocation $location,
        float $quantity,
        string $unitCode,
        ?string $currencyCode = null,
        array $metadata = []
    ): InventoryTransaction {
        $inventoryItem = app(InventoryInterface::class)->createItem($this);
        $inventoryLocation = app(InventoryInterface::class)->createLocation($location);

        return app(InventoryInterface::class)->recordTransaction([
            'reference_type'   => $this->getMorphClass(),
            'reference_id'     => (string) $this->getKey(),
            'transaction_type' => 'adjustment',
            'movements'        => [
                [
                    'item_id'       => $inventoryItem->id,
                    'location_id'   => $inventoryLocation->id,
                    'type'          => 'in', // Adjustment logic handles direction internally in service but needs a type in movement for structure
                    'quantity'      => $quantity,
                    'unit_code'     => $unitCode,
                    'currency_code' => $currencyCode,
                    'metadata'      => $metadata,
                ],
            ],
        ]);
    }
}

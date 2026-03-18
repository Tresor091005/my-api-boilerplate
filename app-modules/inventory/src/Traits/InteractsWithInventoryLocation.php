<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryTransaction;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

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
     * Get all the model's inventory movements through its inventory location.
     */
    public function locationMovements(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations($this->inventoryLocation(), (new InventoryLocation())->movements());
    }

    /**
     * Get the current stock for a specific item at this location.
     */
    public function getStock(?HasInventoryItem $item = null): float
    {
        $query = $this->locationStocks()->where('remaining', '>', 0);

        if ($item) {
            $inventoryItem = app(InventoryInterface::class)->createItem($item);
            $query->where('item_id', $inventoryItem->id);
        }

        return (float) $query->sum('remaining');
    }

    /**
     * Record an inventory 'in' transaction.
     */
    public function recordInventoryIn(
        HasInventoryItem $item,
        float $quantity,
        string $unitCode,
        ?int $unitCost = null,
        ?string $currencyCode = null,
        array $metadata = []
    ): InventoryTransaction {
        $inventoryLocation = app(InventoryInterface::class)->createLocation($this);
        $inventoryItem = app(InventoryInterface::class)->createItem($item);

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
        HasInventoryItem $item,
        float $quantity,
        string $unitCode,
        array $metadata = []
    ): InventoryTransaction {
        $inventoryLocation = app(InventoryInterface::class)->createLocation($this);
        $inventoryItem = app(InventoryInterface::class)->createItem($item);

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
        HasInventoryItem $item,
        float $quantity,
        string $unitCode,
        ?string $currencyCode = null,
        array $metadata = []
    ): InventoryTransaction {
        $inventoryLocation = app(InventoryInterface::class)->createLocation($this);
        $inventoryItem = app(InventoryInterface::class)->createItem($item);

        return app(InventoryInterface::class)->recordTransaction([
            'reference_type'   => $this->getMorphClass(),
            'reference_id'     => (string) $this->getKey(),
            'transaction_type' => 'adjustment',
            'movements'        => [
                [
                    'item_id'       => $inventoryItem->id,
                    'location_id'   => $inventoryLocation->id,
                    'type'          => 'in',
                    'quantity'      => $quantity,
                    'unit_code'     => $unitCode,
                    'currency_code' => $currencyCode,
                    'metadata'      => $metadata,
                ],
            ],
        ]);
    }
}

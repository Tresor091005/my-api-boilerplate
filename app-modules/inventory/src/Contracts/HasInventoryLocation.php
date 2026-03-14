<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lahatre\Inventory\Models\InventoryLocation;

interface HasInventoryLocation
{
    /**
     * @return MorphOne<InventoryLocation, $this>
     */
    public function inventoryLocation(): MorphOne;

    public function getMorphClass(): string;

    /**
     * @return string|int
     */
    public function getKey();
}

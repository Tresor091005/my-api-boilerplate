<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lahatre\Inventory\Models\InventoryItem;

interface HasInventoryItem
{
    /**
     * @return MorphOne<InventoryItem, $this>
     */
    public function inventoryItem(): MorphOne;

    public function getMorphClass(): string;

    /**
     * @return string|int
     */
    public function getKey();
}

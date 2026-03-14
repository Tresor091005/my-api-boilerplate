<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lahatre\Inventory\Models\InventoryLocation;

/**
 * @phpstan-require-extends Model
 */
interface HasInventoryLocation
{
    /**
     * @return MorphOne<InventoryLocation, Model>
     */
    public function inventoryLocation(): MorphOne;

    public function getMorphClass(): string;

    /**
     * @return string|int
     */
    public function getKey();
}

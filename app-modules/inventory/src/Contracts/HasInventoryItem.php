<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lahatre\Inventory\Models\InventoryItem;

/**
 * @phpstan-require-extends Model
 */
interface HasInventoryItem
{
    /**
     * @return MorphOne<InventoryItem, Model>
     */
    public function inventoryItem(): MorphOne;

    public function getMorphClass();

    public function getKey();

    public function getUnitGroupId(): string;
}

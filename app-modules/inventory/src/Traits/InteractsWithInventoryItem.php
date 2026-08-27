<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Models\InventoryItem;

/**
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements HasInventoryItem
 *
 * @mixin Model
 */
trait InteractsWithInventoryItem
{
    /**
     * Get the model's inventory item.
     *
     * @return MorphOne<InventoryItem, Model>
     */
    public function inventoryItem(): MorphOne
    {
        $organizationId = currentOrganizationId();

        /** @var MorphOne<InventoryItem, Model> $relation */
        $relation = $this->morphOne(InventoryItem::class, 'itemable')
            ->where('inventory_items.organization_id', $organizationId);

        return $relation;
    }
}

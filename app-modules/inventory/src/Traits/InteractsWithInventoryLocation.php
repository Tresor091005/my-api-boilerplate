<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Models\InventoryLocation;

/**
 * @phpstan-require-extends Model
 *
 * @phpstan-require-implements HasInventoryLocation
 *
 * @mixin Model
 */
trait InteractsWithInventoryLocation
{
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
}

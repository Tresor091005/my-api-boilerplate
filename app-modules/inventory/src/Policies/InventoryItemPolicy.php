<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Shared\Policies\BasePolicy;

class InventoryItemPolicy extends BasePolicy
{
    public function update(Authorizable $user, InventoryItem $item): bool
    {
        return $this->canOnModel('inventory_items.update', $item);
    }
}

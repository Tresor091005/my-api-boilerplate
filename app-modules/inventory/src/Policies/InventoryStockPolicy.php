<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Shared\Policies\BasePolicy;

class InventoryStockPolicy extends BasePolicy
{
    public function update(Authorizable $user, InventoryStock $stock): bool
    {
        return $this->canOnModel('inventory_stocks.update', $stock);
    }
}

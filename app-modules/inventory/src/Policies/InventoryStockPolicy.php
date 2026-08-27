<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Shared\Policies\BasePolicy;

class InventoryStockPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', InventoryStock::class);
    }

    public function update(Authorizable $user, InventoryStock $stock): bool
    {
        return $this->canOnModel('update', $stock);
    }
}

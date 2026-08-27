<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Shared\Policies\BasePolicy;

class InventoryTransactionPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', InventoryTransaction::class);
    }

    public function retrieve(Authorizable $user, InventoryTransaction $transaction): bool
    {
        return $this->canOnModel('retrieve', $transaction);
    }
}

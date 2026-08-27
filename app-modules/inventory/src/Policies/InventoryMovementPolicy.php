<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Shared\Policies\BasePolicy;

class InventoryMovementPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', InventoryMovement::class);
    }
}

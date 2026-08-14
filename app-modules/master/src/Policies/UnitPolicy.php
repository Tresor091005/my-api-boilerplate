<?php

declare(strict_types=1);

namespace Lahatre\Master\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Shared\Policies\BasePolicy;

class UnitPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user): bool
    {
        return $this->can('units.list');
    }

    /**
     * Determine whether the user can create|update models.
     */
    public function upsert(Authorizable $user): bool
    {
        return $this->can('units.create') || $this->can('units.update');
    }
}

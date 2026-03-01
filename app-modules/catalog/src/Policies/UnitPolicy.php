<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;

class UnitPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user): bool
    {
        return $user->can('units.list');
    }

    /**
     * Determine whether the user can create|update models.
     */
    public function sync(Authorizable $user): bool
    {
        return $user->can('units.create') ||
            $user->can('units.update');
    }
}

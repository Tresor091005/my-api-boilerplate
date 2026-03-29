<?php

declare(strict_types=1);

namespace Lahatre\Master\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;

class UnitPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user): bool
    {
        return authContext()->memberRole()->hasPermissionTo('units.list');
    }

    /**
     * Determine whether the user can create|update models.
     */
    public function sync(Authorizable $user): bool
    {
        return authContext()->memberRole()->hasPermissionTo('units.create') ||
            authContext()->memberRole()->hasPermissionTo('units.update');
    }
}

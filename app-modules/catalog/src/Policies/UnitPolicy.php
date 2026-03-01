<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\Unit;

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
     * Determine whether the user can view the model.
     */
    public function retrieve(Authorizable $user, Unit $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authorizable $user): bool
    {
        return $user->can('units.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authorizable $user, Unit $model): bool
    {
        return $user->can('units.update') &&
            $model->is_builtin === false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authorizable $user, Unit $model): bool
    {
        return $user->can('units.delete') &&
            $model->is_builtin === false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authorizable $user, Unit $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authorizable $user, Unit $model): bool
    {
        return false;
    }
}

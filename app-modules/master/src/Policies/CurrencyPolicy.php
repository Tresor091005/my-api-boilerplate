<?php

declare(strict_types=1);

namespace Lahatre\Master\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Master\Models\Currency;

class CurrencyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user)
    {
        return authContext()->memberRole()->hasPermissionTo('currencies.list');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function retrieve(Authorizable $user, Currency $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authorizable $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authorizable $user, Currency $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authorizable $user, Currency $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authorizable $user, Currency $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authorizable $user, Currency $model): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\Category;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user)
    {
        return $user->can('categories.list');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function retrieve(Authorizable $user, Category $model): bool
    {
        return $user->can('categories.retrieve');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authorizable $user): bool
    {
        return $user->can('categories.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authorizable $user, Category $model): bool
    {
        return $user->can('categories.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authorizable $user, Category $model): bool
    {
        return $user->can('categories.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authorizable $user, Category $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authorizable $user, Category $model): bool
    {
        return false;
    }
}

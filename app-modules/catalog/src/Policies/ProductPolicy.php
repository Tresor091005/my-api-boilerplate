<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\Product;

class ProductPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user): bool
    {
        return $user->can('products.list');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function retrieve(Authorizable $user, Product $model): bool
    {
        return $user->can('products.retrieve');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authorizable $user): bool
    {
        return $user->can('products.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authorizable $user, Product $model): bool
    {
        return $user->can('products.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authorizable $user, Product $model): bool
    {
        return $user->can('products.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authorizable $user, Product $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authorizable $user, Product $model): bool
    {
        return false;
    }
}

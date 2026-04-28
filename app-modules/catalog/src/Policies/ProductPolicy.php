<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\Product;
use Lahatre\Shared\Policies\BasePolicy;

class ProductPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user): bool
    {
        return $this->can('products.list');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function retrieve(Authorizable $user, Product $model): bool
    {
        return $this->canOnModel('products.retrieve', $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authorizable $user): bool
    {
        return $this->can('products.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authorizable $user, Product $model): bool
    {
        return $this->canOnModel('products.update', $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authorizable $user, Product $model): bool
    {
        return $this->canOnModel('products.delete', $model);
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

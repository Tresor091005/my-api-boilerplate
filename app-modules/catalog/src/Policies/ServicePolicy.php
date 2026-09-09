<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\Service;
use Lahatre\Shared\Policies\BasePolicy;

final class ServicePolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', Service::class);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function retrieve(Authorizable $user, Service $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', Service::class);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authorizable $user, Service $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authorizable $user, Service $model): bool
    {
        return $this->canOnModel('delete', $model);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authorizable $user, Service $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authorizable $user, Service $model): bool
    {
        return false;
    }
}

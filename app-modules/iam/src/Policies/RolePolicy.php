<?php

declare(strict_types=1);

namespace Lahatre\Iam\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Iam\Models\Role;
use Lahatre\Shared\Policies\BasePolicy;

class RolePolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', Role::class);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function retrieve(Authorizable $user, Role $model): bool
    {
        return $model->guard_name === config('auth.defaults.guard')
            && $this->canModel('retrieve', $model)
            && ($model->team_id === currentOrganizationId() || ($model->team_id === null && $model->is_builtin));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', Role::class);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authorizable $user, Role $model): bool
    {
        return $model->guard_name === config('auth.defaults.guard')
            && !$model->is_builtin
            && $model->team_id === currentOrganizationId()
            && $this->canModel('update', $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authorizable $user, Role $model): bool
    {
        return $model->guard_name === config('auth.defaults.guard')
            && !$model->is_builtin
            && $model->team_id === currentOrganizationId()
            && $this->canModel('delete', $model);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authorizable $user, Role $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authorizable $user, Role $model): bool
    {
        return false;
    }
}

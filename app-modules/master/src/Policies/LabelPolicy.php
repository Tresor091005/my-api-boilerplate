<?php

declare(strict_types=1);

namespace Lahatre\Master\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Master\Models\Label;
use Lahatre\Shared\Policies\BasePolicy;

class LabelPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', Label::class);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function retrieve(Authorizable $user, Label $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', Label::class);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authorizable $user, Label $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function reorder(Authorizable $user): bool
    {
        return $this->canModel('update', Label::class);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authorizable $user, Label $model): bool
    {
        return $this->canOnModel('delete', $model);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Authorizable $user, Label $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Authorizable $user, Label $model): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Iam\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Iam\Models\User;
use Lahatre\Shared\Policies\BasePolicy;

class UserPolicy extends BasePolicy
{
    public function update(Authorizable $user, User $model): bool
    {
        return $user instanceof User && $user->id === $model->id;
    }

    public function restore(Authorizable $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, User $model): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Billing\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Billing\Models\Plan;

class PlanPolicy
{
    public function list(Authorizable $user): bool
    {
        return $user->can('operate-platform');
    }

    public function retrieve(Authorizable $user, Plan $model): bool
    {
        return $user->can('operate-platform');
    }

    public function create(Authorizable $user): bool
    {
        return $user->can('operate-platform');
    }

    public function update(Authorizable $user, Plan $model): bool
    {
        return $user->can('operate-platform');
    }

    public function delete(Authorizable $user, Plan $model): bool
    {
        return $user->can('operate-platform');
    }

    public function restore(Authorizable $user, Plan $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, Plan $model): bool
    {
        return false;
    }
}

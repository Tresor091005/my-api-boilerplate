<?php

declare(strict_types=1);

namespace Lahatre\Billing\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Billing\Models\Plan;
use Lahatre\Shared\Policies\BasePolicy;

class PlanPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', Plan::class);
    }

    public function retrieve(Authorizable $user, Plan $model): bool
    {
        return $this->canModel('retrieve', Plan::class);
    }

    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', Plan::class);
    }

    public function update(Authorizable $user, Plan $model): bool
    {
        return $this->canModel('update', Plan::class);
    }

    public function delete(Authorizable $user, Plan $model): bool
    {
        return $this->canModel('delete', Plan::class);
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

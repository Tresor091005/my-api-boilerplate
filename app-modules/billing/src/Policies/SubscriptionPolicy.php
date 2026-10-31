<?php

declare(strict_types=1);

namespace Lahatre\Billing\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Shared\Policies\BasePolicy;

class SubscriptionPolicy extends BasePolicy
{
    public function retrieve(Authorizable $user, Subscription $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function pay(Authorizable $user): bool
    {
        return $this->canModel('pay', Subscription::class);
    }

    public function restore(Authorizable $user, Subscription $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, Subscription $model): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Billing\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Billing\Models\Checkout;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Shared\Policies\BasePolicy;

class CheckoutPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', Subscription::class);
    }

    public function retrieve(Authorizable $user, Checkout $model): bool
    {
        return $model->organization_id === currentOrganizationId()
            && $this->canModel('retrieve', Subscription::class);
    }
}

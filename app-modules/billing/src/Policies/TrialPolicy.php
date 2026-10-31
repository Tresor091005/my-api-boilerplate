<?php

declare(strict_types=1);

namespace Lahatre\Billing\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Billing\Models\Trial;
use Lahatre\Shared\Policies\BasePolicy;

class TrialPolicy extends BasePolicy
{
    public function retrieve(Authorizable $user, Trial $model): bool
    {
        return $model->organization_id === currentOrganizationId()
            && $this->canModel('retrieve', Subscription::class);
    }
}

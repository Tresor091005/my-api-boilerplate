<?php

declare(strict_types=1);

namespace Lahatre\Sale\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Sale\Models\Fulfillment;
use Lahatre\Shared\Policies\BasePolicy;

/** Only a draft is deleted, and the service says so; a completed fulfillment is cancelled. */
class FulfillmentPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', Fulfillment::class);
    }

    public function retrieve(Authorizable $user, Fulfillment $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', Fulfillment::class);
    }

    public function update(Authorizable $user, Fulfillment $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function delete(Authorizable $user, Fulfillment $model): bool
    {
        return $this->canOnModel('delete', $model);
    }

    public function complete(Authorizable $user, Fulfillment $model): bool
    {
        return $this->canOnModel('complete', $model);
    }

    public function cancel(Authorizable $user, Fulfillment $model): bool
    {
        return $this->canOnModel('cancel', $model);
    }

    public function restore(Authorizable $user, Fulfillment $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, Fulfillment $model): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Sale\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Sale\Models\Sale;
use Lahatre\Shared\Policies\BasePolicy;

/** A sale is never deleted, so there is no delete ability. */
class SalePolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', Sale::class);
    }

    public function retrieve(Authorizable $user, Sale $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', Sale::class);
    }

    /** Writing a draft, and the gestures that move it back and forth. */
    public function update(Authorizable $user, Sale $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function approve(Authorizable $user, Sale $model): bool
    {
        return $this->canOnModel('approve', $model);
    }

    public function confirm(Authorizable $user, Sale $model): bool
    {
        return $this->canOnModel('confirm', $model);
    }

    public function cancel(Authorizable $user, Sale $model): bool
    {
        return $this->canOnModel('cancel', $model);
    }

    public function delete(Authorizable $user, Sale $model): bool
    {
        return false;
    }

    public function restore(Authorizable $user, Sale $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, Sale $model): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Iam\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Iam\Models\Invitation;
use Lahatre\Shared\Policies\BasePolicy;

class InvitationPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', Invitation::class);
    }

    public function retrieve(Authorizable $user, Invitation $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', Invitation::class);
    }

    public function update(Authorizable $user, Invitation $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function delete(Authorizable $user, Invitation $model): bool
    {
        return $this->canOnModel('delete', $model);
    }

    public function restore(Authorizable $user, Invitation $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, Invitation $model): bool
    {
        return false;
    }
}

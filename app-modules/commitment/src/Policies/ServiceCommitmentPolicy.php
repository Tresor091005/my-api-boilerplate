<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Shared\Policies\BasePolicy;

final class ServiceCommitmentPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', ServiceCommitment::class);
    }

    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', ServiceCommitment::class);
    }

    public function retrieve(Authorizable $user, ServiceCommitment $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function update(Authorizable $user, ServiceCommitment $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function delete(Authorizable $user, ServiceCommitment $model): bool
    {
        return false;
    }

    public function restore(Authorizable $user, ServiceCommitment $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, ServiceCommitment $model): bool
    {
        return false;
    }
}

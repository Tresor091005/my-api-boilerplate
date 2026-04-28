<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\Option;
use Lahatre\Shared\Policies\BasePolicy;

class OptionPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->can('options.list');
    }

    public function retrieve(Authorizable $user, Option $model): bool
    {
        return $this->canOnModel('options.retrieve', $model);
    }

    public function create(Authorizable $user): bool
    {
        return $this->can('options.create');
    }

    public function update(Authorizable $user, Option $model): bool
    {
        return $this->canOnModel('options.update', $model);
    }

    public function delete(Authorizable $user, Option $model): bool
    {
        return $this->canOnModel('options.delete', $model);
    }

    public function restore(Authorizable $user, Option $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, Option $model): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\Option;

class OptionPolicy
{
    public function list(Authorizable $user): bool
    {
        return $user->can('options.list');
    }

    public function retrieve(Authorizable $user, Option $model): bool
    {
        return $user->can('options.retrieve');
    }

    public function create(Authorizable $user): bool
    {
        return $user->can('options.create');
    }

    public function update(Authorizable $user, Option $model): bool
    {
        return $user->can('options.update');
    }

    public function delete(Authorizable $user, Option $model): bool
    {
        return $user->can('options.delete');
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

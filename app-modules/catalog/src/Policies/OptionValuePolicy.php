<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\OptionValue;

class OptionValuePolicy
{
    public function list(Authorizable $user): bool
    {
        return $user->can('option_values.list');
    }

    public function retrieve(Authorizable $user, OptionValue $model): bool
    {
        return $user->can('option_values.retrieve');
    }

    public function create(Authorizable $user): bool
    {
        return $user->can('option_values.create');
    }

    public function update(Authorizable $user, OptionValue $model): bool
    {
        return $user->can('option_values.update');
    }

    public function delete(Authorizable $user, OptionValue $model): bool
    {
        return $user->can('option_values.delete');
    }

    public function restore(Authorizable $user, OptionValue $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, OptionValue $model): bool
    {
        return false;
    }
}

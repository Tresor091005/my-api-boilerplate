<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Shared\Policies\BasePolicy;

class OptionValuePolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', OptionValue::class);
    }

    public function retrieve(Authorizable $user, OptionValue $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', OptionValue::class);
    }

    public function update(Authorizable $user, OptionValue $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function delete(Authorizable $user, OptionValue $model): bool
    {
        return $this->canOnModel('delete', $model);
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

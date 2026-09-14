<?php

declare(strict_types=1);

namespace Lahatre\Library\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Library\Models\File;
use Lahatre\Shared\Policies\BasePolicy;

class FilePolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', File::class);
    }

    public function retrieve(Authorizable $user, File $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', File::class);
    }

    public function update(Authorizable $user, File $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function delete(Authorizable $user, File $model): bool
    {
        return $this->canOnModel('delete', $model);
    }

    public function restore(Authorizable $user, File $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function forceDelete(Authorizable $user, File $model): bool
    {
        return false;
    }
}

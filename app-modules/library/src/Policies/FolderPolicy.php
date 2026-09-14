<?php

declare(strict_types=1);

namespace Lahatre\Library\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Library\Models\Folder;
use Lahatre\Shared\Policies\BasePolicy;

class FolderPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', Folder::class);
    }

    public function retrieve(Authorizable $user, Folder $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function create(Authorizable $user): bool
    {
        return $this->canModel('create', Folder::class);
    }

    public function update(Authorizable $user, Folder $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function delete(Authorizable $user, Folder $model): bool
    {
        return $this->canOnModel('delete', $model);
    }

    public function restore(Authorizable $user, Folder $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, Folder $model): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Iam\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Iam\Models\Permission;
use Lahatre\Shared\Policies\BasePolicy;

class PermissionPolicy extends BasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', Permission::class);
    }
}

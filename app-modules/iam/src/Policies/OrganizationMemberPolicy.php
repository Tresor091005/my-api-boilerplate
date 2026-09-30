<?php

declare(strict_types=1);

namespace Lahatre\Iam\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Shared\Policies\BasePolicy;

class OrganizationMemberPolicy extends BasePolicy
{
    public function list(Authorizable $user): bool
    {
        return $this->canModel('list', OrganizationMember::class);
    }

    public function retrieve(Authorizable $user, OrganizationMember $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function update(Authorizable $user, OrganizationMember $model): bool
    {
        return $this->canOnModel('update', $model);
    }

    public function delete(Authorizable $user, OrganizationMember $model): bool
    {
        return $this->canOnModel('delete', $model);
    }

    public function restore(Authorizable $user, OrganizationMember $model): bool
    {
        return false;
    }

    public function forceDelete(Authorizable $user, OrganizationMember $model): bool
    {
        return false;
    }
}

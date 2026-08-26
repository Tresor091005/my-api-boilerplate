<?php

declare(strict_types=1);

namespace Lahatre\Organization\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Organization\Models\OrganizationSetting;
use Lahatre\Shared\Policies\BasePolicy;

class OrganizationSettingPolicy extends BasePolicy
{
    public function retrieve(Authorizable $user, OrganizationSetting $model): bool
    {
        return $this->canOnModel('retrieve', $model);
    }

    public function update(Authorizable $user, OrganizationSetting $model): bool
    {
        return $this->canOnModel('update', $model);
    }
}

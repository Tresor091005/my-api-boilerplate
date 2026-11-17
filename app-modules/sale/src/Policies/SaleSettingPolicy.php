<?php

declare(strict_types=1);

namespace Lahatre\Sale\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Lahatre\Sale\Models\SaleSetting;
use Lahatre\Shared\Policies\BasePolicy;

/** Settings belong to the current organization, so abilities are checked on the class. */
class SaleSettingPolicy extends BasePolicy
{
    public function retrieve(Authorizable $user): bool
    {
        return $this->canModel('retrieve', SaleSetting::class);
    }

    public function update(Authorizable $user): bool
    {
        return $this->canModel('update', SaleSetting::class);
    }
}

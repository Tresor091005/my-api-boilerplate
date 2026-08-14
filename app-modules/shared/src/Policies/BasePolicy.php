<?php

declare(strict_types=1);

namespace Lahatre\Shared\Policies;

use Illuminate\Database\Eloquent\Model;

abstract class BasePolicy
{
    protected function can(string $permission): bool
    {
        return authContext()->memberRole()->hasPermissionTo($permission);
    }

    protected function canOnModel(string $permission, Model $model): bool
    {
        return isset($model->organization_id)
            && $model->organization_id === currentOrganizationId()
            && $this->can($permission);
    }
}

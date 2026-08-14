<?php

declare(strict_types=1);

namespace Lahatre\Shared\Policies;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Shared\Registries\MorphMapRegistry;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

abstract class BasePolicy
{
    protected function can(string $permission): bool
    {
        try {
            return authContext()->memberRole()->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /** @param class-string<Model>|Model $model */
    protected function permissionName(string $action, string|Model $model): ?string
    {
        $alias = app(MorphMapRegistry::class)->getAlias($model);

        return $alias === null ? null : "{$alias}.{$action}";
    }

    /** @param class-string<Model>|Model $model */
    protected function canModel(string $action, string|Model $model): bool
    {
        $permission = $this->permissionName($action, $model);

        return $permission !== null && $this->can($permission);
    }

    protected function canOnModel(string $action, Model $model): bool
    {
        return isset($model->organization_id)
            && $model->organization_id === currentOrganizationId()
            && $this->canModel($action, $model);
    }
}

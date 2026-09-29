<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Database\Eloquent\Collection;
use Lahatre\Iam\Models\Permission;

class PermissionService
{
    /** @return Collection<int, Permission> */
    public function all(): Collection
    {
        return Permission::query()
            ->where('guard_name', config('auth.defaults.guard'))
            ->orderBy('name')
            ->get();
    }
}

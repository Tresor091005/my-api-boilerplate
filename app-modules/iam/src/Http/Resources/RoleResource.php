<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\ResponseSelection\ResourceDefinition;

class RoleResource extends RoleSummaryResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        parent::define($resource);

        // permissions grow with the code, not with user data: the whole catalog must fit,
        // since an editor sends `permission_ids` back whole
        $resource->include('permissions', PermissionResource::class, limit: 500);
    }
}

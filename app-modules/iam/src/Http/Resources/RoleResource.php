<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\ResponseSelection\ResourceDefinition;

class RoleResource extends RoleSummaryResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        parent::define($resource);

        $resource->include('permissions', PermissionResource::class);
    }
}

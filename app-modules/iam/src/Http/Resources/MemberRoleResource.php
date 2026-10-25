<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\Iam\Http\Resources\Loaders\AssignmentRoles;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class MemberRoleResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('role_id')
            ->field('is_active')
            ->field('created_at')
            ->field('updated_at')
            // the role only when it is usable in the member role's organization
            ->virtualInclude('role', RoleSummaryResource::class, loader: AssignmentRoles::class, columns: MemberRole::ROLE_CONTEXT_COLUMNS);
    }
}

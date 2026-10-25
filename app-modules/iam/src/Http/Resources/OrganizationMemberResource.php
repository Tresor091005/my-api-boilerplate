<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class OrganizationMemberResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('is_active')
            ->field('created_at')
            ->field('updated_at')
            ->include('user', UserProfileResource::class)
            ->include('member_roles', MemberRoleResource::class, relation: 'organizationMemberRoles');
    }
}

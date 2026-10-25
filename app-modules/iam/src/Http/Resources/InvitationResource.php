<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class InvitationResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('email')
            ->field('expires_at')
            ->field('accepted_at')
            ->field('created_at')
            ->field('updated_at')
            ->include('roles', RoleSummaryResource::class, relation: 'organizationRoles');
    }
}

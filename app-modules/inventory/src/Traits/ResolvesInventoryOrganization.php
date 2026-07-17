<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Traits;

use Lahatre\Inventory\Exceptions\OrganizationScopeException;

trait ResolvesInventoryOrganization
{
    protected function organizationId(): string
    {
        $organizationId = getPermissionsTeamId();

        if (!is_string($organizationId) || $organizationId === '') {
            throw OrganizationScopeException::contextRequired();
        }

        return $organizationId;
    }
}

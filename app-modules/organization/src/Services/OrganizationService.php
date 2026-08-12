<?php

declare(strict_types=1);

namespace Lahatre\Organization\Services;

use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Organization\Models\Organization;

class OrganizationService implements OrganizationInterface
{
    public function initializeOrganization(array $data): Organization
    {
        return new Organization;
    }

    public function findOrganizationById(string $organizationId): Organization
    {
        return Organization::query()->findOrFail($organizationId);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Organization\Contracts;

use Lahatre\Organization\Models\Organization;

interface OrganizationInterface
{
    public function initializeOrganization(array $data): Organization;

    public function findOrganizationById(string $organizationId): ?Organization;
}

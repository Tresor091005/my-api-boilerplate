<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class OrganizationScopeException extends AssertionException
{
    public static function mismatch(string $expectedOrganizationId, ?string $actualOrganizationId): self
    {
        return new self(
            __('inventory::exceptions.organization_mismatch'),
            [
                'expected_organization_id' => $expectedOrganizationId,
                'actual_organization_id'   => $actualOrganizationId,
            ]
        );
    }
}

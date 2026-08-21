<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class OrganizationScopeException extends AssertionException
{
    public static function resolutionFailed(): self
    {
        return new self(
            __('inventory::exceptions.organization_resolution_failed'),
        );
    }

    public static function mismatch(): self
    {
        return new self(
            __('inventory::exceptions.organization_mismatch'),
        );
    }
}

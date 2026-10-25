<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * A role shown under an invitation or a member role: its permissions are read through the role routes only.
 */
class RoleSummaryResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('description')
            ->field('is_builtin')
            ->field('is_active');
    }
}

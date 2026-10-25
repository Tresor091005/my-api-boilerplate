<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * Another user as organization members see them: no id and no account details.
 */
class UserProfileResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('first_name')
            ->field('last_name')
            ->field('email');
    }
}

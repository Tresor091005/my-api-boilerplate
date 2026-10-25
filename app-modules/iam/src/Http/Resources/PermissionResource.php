<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class PermissionResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('title')
            ->field('description');
    }
}

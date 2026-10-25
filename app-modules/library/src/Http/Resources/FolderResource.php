<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class FolderResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('parent_id')
            ->field('created_at')
            ->field('updated_at');
    }
}

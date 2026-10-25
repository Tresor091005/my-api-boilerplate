<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class InventoryLocationResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('external_type')
            ->field('external_id')
            ->field('is_active')
            ->field('created_at')
            ->field('updated_at');
    }
}

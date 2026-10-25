<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class UnitGroupResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('is_builtin')
            ->include('units', UnitResource::class, relation: 'visibleUnits');
    }
}

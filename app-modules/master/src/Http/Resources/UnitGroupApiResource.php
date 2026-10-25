<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * Unit group rendered through response selection. UnitGroupResource stays for modules not migrated yet.
 */
final class UnitGroupApiResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('is_builtin')
            ->include('units', UnitApiResource::class);
    }
}

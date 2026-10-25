<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * Unit rendered through response selection. UnitResource stays for modules not migrated yet.
 */
final class UnitApiResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('code')
            ->field('name')
            ->field('ratio')
            ->field('symbol')
            ->include('group', UnitGroupApiResource::class);
    }
}

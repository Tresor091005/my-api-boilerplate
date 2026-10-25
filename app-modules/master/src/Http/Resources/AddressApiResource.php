<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * Address rendered through response selection. AddressResource stays for modules not migrated yet.
 */
final class AddressApiResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('line')
            ->field('city')
            ->field('country')
            ->field('is_primary');
    }
}

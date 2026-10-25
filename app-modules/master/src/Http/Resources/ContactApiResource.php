<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * Contact rendered through response selection. ContactResource stays for modules not migrated yet.
 */
final class ContactApiResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('type')
            ->field('value')
            ->field('is_primary');
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * Label rendered through response selection. LabelResource stays for modules not migrated yet.
 */
final class LabelApiResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('value')
            ->field('slug')
            ->field('group')
            ->field('order_col')
            ->field('created_at')
            ->field('updated_at');
    }
}

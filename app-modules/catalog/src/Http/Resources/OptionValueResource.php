<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class OptionValueResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('option_id')
            ->field('value')
            ->field('created_at')
            ->field('updated_at')
            ->include('option', OptionResource::class);
    }
}

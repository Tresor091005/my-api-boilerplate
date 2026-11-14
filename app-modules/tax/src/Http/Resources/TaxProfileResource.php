<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class TaxProfileResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('code')
            ->field('name')
            ->field('is_active')
            ->field('created_at')
            ->field('updated_at')
            ->include('lines', TaxProfileLineResource::class);
    }
}

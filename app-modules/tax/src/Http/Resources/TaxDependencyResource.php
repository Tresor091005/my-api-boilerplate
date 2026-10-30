<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class TaxDependencyResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('depends_on_tax_id')
            ->field('operator')
            ->include('depends_on_tax', TaxResource::class);
    }
}

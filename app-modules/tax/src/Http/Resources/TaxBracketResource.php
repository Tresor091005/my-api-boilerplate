<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class TaxBracketResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('base_min')
            ->field('base_max')
            ->field('value')
            ->field('tax_amount_min')
            ->field('tax_amount_max');
    }
}

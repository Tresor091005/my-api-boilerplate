<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class TaxProfileLineResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('tax_id')
            ->field('treatment')
            ->include('tax', TaxResource::class)
            ->include('bracket_overrides', TaxProfileLineBracketResource::class);
    }
}

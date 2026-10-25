<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class CurrencyResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('code')
            ->field('name')
            ->field('symbol')
            ->field('precision');
    }
}

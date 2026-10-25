<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class ExchangeRateResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('source_currency_code')
            ->field('target_currency_code')
            ->field('context')
            ->field('rate')
            ->field('effective_at')
            ->field('created_at')
            ->field('updated_at');
    }
}

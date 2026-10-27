<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Master\Support\CurrencyNames;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class CurrencyResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('code')
            ->field('name', columns: ['code'], computedBy: fn (Model $currency): string => CurrencyNames::name((string) $currency->getAttribute('code'), app()->getLocale()))
            ->field('symbol')
            ->field('precision');
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

final class ExampleTransferLineResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('sku')
            ->field('quantity')
            ->field('unit_cost', columns: ['unit_cost_minor'], computedBy: fn (Model $line): float => $line->getAttribute('unit_cost_minor') / 100);
    }
}

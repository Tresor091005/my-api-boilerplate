<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

final class ExampleDepotResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('code')
            ->field('badge', columns: ['code', 'name'], computedBy: fn (Model $depot): string => "[{$depot->getAttribute('code')}] {$depot->getAttribute('name')}", default: false)
            ->virtualInclude('open_transfers', ExampleTransferResource::class, ExampleOpenTransfers::class, asList: true);
    }
}

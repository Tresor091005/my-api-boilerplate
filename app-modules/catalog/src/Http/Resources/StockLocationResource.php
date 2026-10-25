<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Master\Http\Resources\AddressResource;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class StockLocationResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('handle')
            ->field('name')
            ->field('is_active', relations: ['inventoryLocation'], computedBy: fn (Model $location): ?bool => $location->getRelation('inventoryLocation')?->getAttribute('is_active'))
            ->field('created_at')
            ->field('updated_at')
            ->include('address', AddressResource::class);
    }
}

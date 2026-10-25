<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Catalog\Http\Resources\Fields\BundleItemQuantities;
use Lahatre\Catalog\Http\Resources\Resolvers\BundleComponentResolver;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class BundleItemResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('item_type')
            ->field('item_id')
            // stored in base units, shown in the item's display unit
            ->field('quantity', columns: ['quantity', 'display_unit_code'], computedBy: BundleItemQuantities::class)
            ->field('unit_code', columns: ['display_unit_code'], computedBy: fn (Model $item): ?string => $item->getAttribute('display_unit_code'))
            ->field('created_at')
            ->field('updated_at')
            ->include('component', BundleComponentResolver::class);
    }
}

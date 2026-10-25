<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Lahatre\Catalog\Http\Resources\Resolvers\StockTransferItemResolver;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class StockTransferLineResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('catalog_item_type')
            ->field('catalog_item_id')
            ->field('position')
            ->field('quantity')
            ->field('display_unit_code')
            ->field('strategy')
            ->field('stock_ids')
            ->include('item', StockTransferItemResolver::class);
    }
}

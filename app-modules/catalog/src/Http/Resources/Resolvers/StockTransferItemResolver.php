<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Catalog\Http\Resources\BundleResource;
use Lahatre\Catalog\Http\Resources\ProductVariantResource;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\ResponseSelection\ResourceResolver;

/**
 * Renders the item a stock transfer line moves: a product variant or a bundle.
 */
final class StockTransferItemResolver implements ResourceResolver
{
    public function resourceFor(Model $model): ?string
    {
        return match (true) {
            $model instanceof ProductVariant => ProductVariantResource::class,
            $model instanceof Bundle         => BundleResource::class,
            default                          => null,
        };
    }

    public function resources(): array
    {
        return [ProductVariantResource::class, BundleResource::class];
    }
}

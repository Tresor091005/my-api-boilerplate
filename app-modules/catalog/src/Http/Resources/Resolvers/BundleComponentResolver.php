<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Catalog\Http\Resources\ProductVariantResource;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\ResponseSelection\ResourceResolver;

/**
 * Renders a bundle component: only product variants have a representation.
 */
final class BundleComponentResolver implements ResourceResolver
{
    public function resourceFor(Model $model): ?string
    {
        return $model instanceof ProductVariant ? ProductVariantResource::class : null;
    }

    public function resources(): array
    {
        return [ProductVariantResource::class];
    }
}

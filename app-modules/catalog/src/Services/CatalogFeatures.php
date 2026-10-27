<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Lahatre\Billing\Contracts\ProvidesFeatures;
use Lahatre\Billing\Registries\FeatureDefinition;
use Lahatre\Catalog\Models\Product;

final class CatalogFeatures implements ProvidesFeatures
{
    public function definitions(): array
    {
        return [
            FeatureDefinition::boolean('catalog.bundles'),
            FeatureDefinition::capacity('catalog.products', [self::class, 'products']),
        ];
    }

    public function products(string $organizationId): int
    {
        return Product::query()->where('organization_id', $organizationId)->count();
    }
}

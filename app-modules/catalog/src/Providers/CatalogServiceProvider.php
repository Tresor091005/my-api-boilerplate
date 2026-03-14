<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Shared\Registries\MorphMapRegistry;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(MorphMapRegistry $registry): void
    {
        $registry->register([
            'product_variant' => ProductVariant::class,
            'bundle'          => Bundle::class,
        ]);
    }
}

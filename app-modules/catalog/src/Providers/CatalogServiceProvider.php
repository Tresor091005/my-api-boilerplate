<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Billing\Registries\FeatureRegistry;
use Lahatre\Catalog\Services\CatalogFeatures;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(FeatureRegistry $features): void
    {
        $features->registerModule(CatalogFeatures::class);
    }
}

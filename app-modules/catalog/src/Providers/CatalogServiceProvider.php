<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Billing\Registries\FeatureRegistry;
use Lahatre\Catalog\Contracts\PriceLevelUsageInterface;
use Lahatre\Catalog\Services\CatalogFeatures;
use Lahatre\Catalog\Services\CatalogTaxProfileUsage;
use Lahatre\Catalog\Services\CombinedPriceLevelUsage;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PriceLevelUsageInterface::class, fn ($app): CombinedPriceLevelUsage => new CombinedPriceLevelUsage(
            $app->tagged(PriceLevelUsageInterface::SOURCES_TAG),
        ));
    }

    public function boot(FeatureRegistry $features): void
    {
        $features->registerModule(CatalogFeatures::class);
        $this->app->tag([CatalogTaxProfileUsage::class], TaxProfileUsageInterface::SOURCES_TAG);
    }
}

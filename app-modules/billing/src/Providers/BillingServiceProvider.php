<?php

declare(strict_types=1);

namespace Lahatre\Billing\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Billing\CapacityResolverRegistry;
use Lahatre\Billing\Console\Commands\SyncFeatures;
use Lahatre\Billing\FeatureCatalog;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureCatalog::class);
        $this->app->singleton(CapacityResolverRegistry::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncFeatures::class,
            ]);
        }
    }
}

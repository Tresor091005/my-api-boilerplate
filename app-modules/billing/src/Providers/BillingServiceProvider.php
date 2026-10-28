<?php

declare(strict_types=1);

namespace Lahatre\Billing\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Billing\Console\Commands\CheckFeaturesCommand;
use Lahatre\Billing\Registries\FeatureRegistry;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureRegistry::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckFeaturesCommand::class,
            ]);
        }
    }
}

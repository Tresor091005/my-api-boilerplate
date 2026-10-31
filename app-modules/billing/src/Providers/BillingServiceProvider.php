<?php

declare(strict_types=1);

namespace Lahatre\Billing\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Billing\Console\Commands\CheckFeaturesCommand;
use Lahatre\Billing\Console\Commands\ConfirmCheckoutCommand;
use Lahatre\Billing\Registries\FeatureRegistry;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/billing.php', 'billing');
        $this->app->singleton(FeatureRegistry::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckFeaturesCommand::class,
                ...($this->app->isProduction() ? [] : [ConfirmCheckoutCommand::class]),
            ]);
        }
    }
}

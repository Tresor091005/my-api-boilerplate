<?php

declare(strict_types=1);

namespace Lahatre\Billing\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Lahatre\Billing\Console\Commands\CheckFeaturesCommand;
use Lahatre\Billing\Console\Commands\ConfirmCheckoutCommand;
use Lahatre\Billing\Http\Middleware\EnsureSubscriptionAccess;
use Lahatre\Billing\Registries\FeatureRegistry;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/billing.php', 'billing');
        $this->app->singleton(FeatureRegistry::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('billing.subscription', EnsureSubscriptionAccess::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckFeaturesCommand::class,
                ...($this->app->isProduction() ? [] : [ConfirmCheckoutCommand::class]),
            ]);
        }
    }
}

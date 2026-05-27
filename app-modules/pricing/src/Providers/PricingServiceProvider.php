<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Pricing\Contracts\PricingInterface;
use Lahatre\Pricing\Services\PricingService;

class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PricingInterface::class, PricingService::class);
    }

    public function boot(): void {}
}

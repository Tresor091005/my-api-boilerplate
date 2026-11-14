<?php

declare(strict_types=1);

namespace Lahatre\Customer\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Catalog\Contracts\PriceLevelUsageInterface;
use Lahatre\Customer\Services\CustomerPriceLevelUsage;

class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->app->tag([CustomerPriceLevelUsage::class], PriceLevelUsageInterface::SOURCES_TAG);
    }
}

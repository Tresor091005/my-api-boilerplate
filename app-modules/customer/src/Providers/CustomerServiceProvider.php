<?php

declare(strict_types=1);

namespace Lahatre\Customer\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Catalog\Contracts\PriceLevelUsageInterface;
use Lahatre\Customer\Contracts\CustomerInterface;
use Lahatre\Customer\Services\CustomerPriceLevelUsage;
use Lahatre\Customer\Services\CustomerService;

class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerInterface::class, CustomerService::class);
    }

    public function boot(): void
    {
        $this->app->tag([CustomerPriceLevelUsage::class], PriceLevelUsageInterface::SOURCES_TAG);
    }
}

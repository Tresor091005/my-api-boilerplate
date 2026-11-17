<?php

declare(strict_types=1);

namespace Lahatre\Customer\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Catalog\Contracts\PriceLevelUsageInterface;
use Lahatre\Customer\Contracts\CustomerUsageInterface;
use Lahatre\Customer\Contracts\CustomerInterface;
use Lahatre\Customer\Services\CombinedCustomerUsage;
use Lahatre\Customer\Services\CustomerPriceLevelUsage;
use Lahatre\Customer\Services\CustomerService;

class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerInterface::class, CustomerService::class);
        $this->app->bind(CustomerUsageInterface::class, fn ($app): CombinedCustomerUsage => new CombinedCustomerUsage(
            $app->tagged(CustomerUsageInterface::SOURCES_TAG),
        ));
    }

    public function boot(): void
    {
        $this->app->tag([CustomerPriceLevelUsage::class], PriceLevelUsageInterface::SOURCES_TAG);
    }
}

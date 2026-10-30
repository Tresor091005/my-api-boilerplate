<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Catalog\Services\CatalogTaxProfileUsage;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->app->bind(TaxProfileUsageInterface::class, CatalogTaxProfileUsage::class);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Tax\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Tax\Contracts\TaxInterface;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;
use Lahatre\Tax\Services\Engine\CompiledTaxProfileCache;
use Lahatre\Tax\Services\FunctionalCurrencyResolver;
use Lahatre\Tax\Services\NoTaxProfileUsage;
use Lahatre\Tax\Services\TaxResolutionService;

class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TaxInterface::class, TaxResolutionService::class);
        $this->app->scoped(FunctionalCurrencyResolver::class);
        $this->app->scoped(CompiledTaxProfileCache::class);
        // A module assigning profiles rebinds this in its provider's boot().
        $this->app->bindIf(TaxProfileUsageInterface::class, NoTaxProfileUsage::class);
    }

    public function boot(): void {}
}

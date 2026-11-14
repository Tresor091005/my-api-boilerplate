<?php

declare(strict_types=1);

namespace Lahatre\Tax\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Tax\Contracts\TaxInterface;
use Lahatre\Tax\Contracts\TaxProfileUsageInterface;
use Lahatre\Tax\Services\CombinedTaxProfileUsage;
use Lahatre\Tax\Services\Engine\CompiledTaxProfileCache;
use Lahatre\Tax\Services\TaxResolutionService;

class TaxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TaxInterface::class, TaxResolutionService::class);
        $this->app->scoped(CompiledTaxProfileCache::class);
        $this->app->bind(TaxProfileUsageInterface::class, fn ($app): CombinedTaxProfileUsage => new CombinedTaxProfileUsage(
            $app->tagged(TaxProfileUsageInterface::SOURCES_TAG),
        ));
    }

    public function boot(): void {}
}

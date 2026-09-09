<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Catalog\Contracts\CatalogInterface;
use Lahatre\Catalog\Services\CatalogService;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CatalogInterface::class, CatalogService::class);
    }

    public function boot(): void {}
}

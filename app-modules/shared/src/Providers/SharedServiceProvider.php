<?php

declare(strict_types=1);

namespace Lahatre\Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Shared\Registries\MorphMapRegistry;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(MorphMapRegistry::class);
    }

    public function boot(): void {}
}

<?php

declare(strict_types=1);

namespace Lahatre\Shared\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Shared\Console\Commands\MorphMapCacheCommand;
use Lahatre\Shared\Console\Commands\MorphMapClearCommand;
use Lahatre\Shared\Registries\MorphMapRegistry;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(MorphMapRegistry::class);

        $this->commands([
            MorphMapCacheCommand::class,
            MorphMapClearCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->optimizes(
            optimize: 'morph-map:cache',
            clear: 'morph-map:clear',
        );
    }
}

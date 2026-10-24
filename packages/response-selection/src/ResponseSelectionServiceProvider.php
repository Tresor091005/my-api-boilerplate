<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Illuminate\Support\ServiceProvider;
use Lahatre\ResponseSelection\Console\ResponseDeclarationCacheCommand;
use Lahatre\ResponseSelection\Console\ResponseDeclarationClearCommand;
use Lahatre\ResponseSelection\Declaration\ResponseDeclarationRegistry;
use Lahatre\ResponseSelection\Rendering\ModelViolationReporter;
use Lahatre\ResponseSelection\Rendering\ResponseContext;

final class ResponseSelectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/response-selection.php', 'response-selection');

        $this->app->scoped(ResponseContext::class);
        $this->app->scoped(ModelViolationReporter::class);
        // Kept across requests (Octane): declarations do not change while the process runs
        $this->app->singleton(ResponseDeclarationRegistry::class);

        $this->commands([
            ResponseDeclarationCacheCommand::class,
            ResponseDeclarationClearCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'response-selection');
        $this->publishes([__DIR__.'/../config/response-selection.php' => config_path('response-selection.php')], 'response-selection-config');

        if (config('response-selection.tolerate_model_violations') ?? $this->app->isProduction()) {
            ModelViolationReporter::tolerate();
        }

        $this->optimizes(
            optimize: 'response-declarations:cache',
            clear: 'response-declarations:clear',
            key: 'response-declarations',
        );
    }
}

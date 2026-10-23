<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Illuminate\Support\ServiceProvider;
use Lahatre\ResponseSelection\Console\ResponseDeclarationCacheCommand;
use Lahatre\ResponseSelection\Console\ResponseDeclarationClearCommand;

/**
 * Registers the engine. The application attaches ResolveResponseSelection to the middleware group
 * of its API routes itself.
 */
final class ResponseSelectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ResponseContext::class);
        $this->app->scoped(ModelViolationReporter::class);
        // Declarations derive from source code and routes, fixed for the process: a singleton keeps
        // the lazily analyzed actions across requests (Octane) instead of parsing them again.
        $this->app->singleton(ResponseDeclarationRegistry::class);

        $this->commands([
            ResponseDeclarationCacheCommand::class,
            ResponseDeclarationClearCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'response-selection');

        if ($this->app->isProduction()) {
            ModelViolationReporter::tolerate();
        }

        $this->optimizes(
            optimize: 'response-declarations:cache',
            clear: 'response-declarations:clear',
            key: 'response-declarations',
        );
    }
}

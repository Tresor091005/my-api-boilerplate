<?php

declare(strict_types=1);

namespace Lahatre\Shared\Providers;

use Closure;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\ClassMakeCommand;
use Illuminate\Foundation\Console\EnumMakeCommand;
use Illuminate\Foundation\Console\InterfaceMakeCommand;
use Illuminate\Foundation\Console\JobMiddlewareMakeCommand;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Foundation\Console\ScopeMakeCommand;
use Illuminate\Foundation\Console\TestMakeCommand;
use Illuminate\Foundation\Console\TraitMakeCommand;
use Illuminate\Foundation\Console\ViewMakeCommand;
use Illuminate\Routing\Console\ControllerMakeCommand;
use Illuminate\Support\ServiceProvider;
use Lahatre\Shared\Console\Commands\HelpersListCommand;
use Lahatre\Shared\Console\Commands\Make\MakeClass;
use Lahatre\Shared\Console\Commands\Make\MakeController;
use Lahatre\Shared\Console\Commands\Make\MakeEnum;
use Lahatre\Shared\Console\Commands\Make\MakeInterface;
use Lahatre\Shared\Console\Commands\Make\MakeJobMiddleware;
use Lahatre\Shared\Console\Commands\Make\MakeModel;
use Lahatre\Shared\Console\Commands\Make\MakeScope;
use Lahatre\Shared\Console\Commands\Make\MakeTest;
use Lahatre\Shared\Console\Commands\Make\MakeTrait;
use Lahatre\Shared\Console\Commands\Make\MakeView;
use Lahatre\Shared\Console\Commands\MorphMapCacheCommand;
use Lahatre\Shared\Console\Commands\MorphMapClearCommand;
use Lahatre\Shared\Console\Commands\ResponseContractCacheCommand;
use Lahatre\Shared\Console\Commands\ResponseContractClearCommand;
use Lahatre\Shared\Console\Commands\ResponseDeclarationCacheCommand;
use Lahatre\Shared\Console\Commands\ResponseDeclarationClearCommand;
use Lahatre\Shared\Http\Response\ModelViolationReporter;
use Lahatre\Shared\Http\Response\ResponseContext as SelectionResponseContext;
use Lahatre\Shared\Http\Response\ResponseDeclarationRegistry;
use Lahatre\Shared\Http\Responses\ResponseContext;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Lahatre\Shared\Registries\MorphMapRegistry;
use Lahatre\Shared\Registries\ResponseContractRegistry;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/business-numbering.php', 'business-numbering');

        $this->app->scoped(MorphMapRegistry::class);
        $this->app->scoped(ResponseContext::class);
        $this->app->scoped(ResponseContractRegistry::class);
        $this->app->scoped(ResponseResponder::class);
        $this->app->scoped(ModelViolationReporter::class);
        $this->app->scoped(SelectionResponseContext::class);
        // Declarations derive from source code and routes, fixed for the process: a singleton keeps
        // the lazily analyzed actions across requests (Octane) instead of parsing them again.
        $this->app->singleton(ResponseDeclarationRegistry::class);

        $this->commands([
            HelpersListCommand::class,
            MorphMapCacheCommand::class,
            MorphMapClearCommand::class,
            ResponseContractCacheCommand::class,
            ResponseContractClearCommand::class,
            ResponseDeclarationCacheCommand::class,
            ResponseDeclarationClearCommand::class,
        ]);
    }

    public function boot(
        MorphMapRegistry $morphMapRegistry,
        ResponseContractRegistry $responseContractRegistry,
    ): void {
        $morphMapRegistry->discover();
        $responseContractRegistry->discover();

        if ($this->app->isProduction()) {
            ModelViolationReporter::tolerate();
        }

        $this->extendNativeGeneratorCommands();
        Artisan::starting(function (Artisan $artisan): void {
            $artisan->add($this->app->make(MakeTest::class));
        });

        $this->optimizes(
            optimize: 'morph-map:cache',
            clear: 'morph-map:clear',
            key: 'morph-map',
        );
        $this->optimizes(
            optimize: 'response-contracts:cache',
            clear: 'response-contracts:clear',
            key: 'response-contracts',
        );
        $this->optimizes(
            optimize: 'response-declarations:cache',
            clear: 'response-declarations:clear',
            key: 'response-declarations',
        );
    }

    protected function extendNativeGeneratorCommands(): void
    {
        $this->app->extend(ClassMakeCommand::class, $this->makeGeneratorFactory(MakeClass::class));
        $this->app->extend(ControllerMakeCommand::class, $this->makeGeneratorFactory(MakeController::class));
        $this->app->extend(EnumMakeCommand::class, $this->makeGeneratorFactory(MakeEnum::class));
        $this->app->extend(InterfaceMakeCommand::class, $this->makeGeneratorFactory(MakeInterface::class));
        $this->app->extend(JobMiddlewareMakeCommand::class, $this->makeGeneratorFactory(MakeJobMiddleware::class));
        $this->app->extend(ModelMakeCommand::class, $this->makeGeneratorFactory(MakeModel::class));
        $this->app->extend(ScopeMakeCommand::class, $this->makeGeneratorFactory(MakeScope::class));
        $this->app->extend(TraitMakeCommand::class, $this->makeGeneratorFactory(MakeTrait::class));
        $this->app->extend(TestMakeCommand::class, $this->makeGeneratorFactory(MakeTest::class));
        $this->app->extend(ViewMakeCommand::class, $this->makeGeneratorFactory(MakeView::class));
    }

    /**
     * @param  class-string  $generatorClass
     */
    protected function makeGeneratorFactory(string $generatorClass): Closure
    {
        return fn (object $command, Application $app): object => new $generatorClass($app['files']);
    }
}

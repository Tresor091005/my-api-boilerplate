<?php

declare(strict_types=1);

namespace Lahatre\Library\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Lahatre\Library\Console\Commands\ReconcileLibraryCommand;
use Lahatre\Library\Contracts\LibraryInterface;
use Lahatre\Library\Services\LibraryService;

class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/library.php', 'library');
        $this->app->scoped(LibraryInterface::class, LibraryService::class);
        $this->commands([
            ReconcileLibraryCommand::class,
        ]);
    }

    public function boot(Schedule $schedule): void
    {
        $schedule
            ->command('library:reconcile --purge-only')
            ->dailyAt('02:15')
            ->onOneServer()
            ->runInBackground()
            ->withoutOverlapping();

        $schedule
            ->command('library:reconcile --orphans-only --delete-orphans')
            ->weeklyOn(0, '03:15')
            ->onOneServer()
            ->runInBackground()
            ->withoutOverlapping();
    }
}

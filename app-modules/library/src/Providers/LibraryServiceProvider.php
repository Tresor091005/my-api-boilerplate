<?php

declare(strict_types=1);

namespace Lahatre\Library\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Lahatre\Library\Console\Commands\ReconcileLibraryCommand;
use Lahatre\Library\Console\Commands\VerifyLibraryChecksumsCommand;

class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/library.php', 'library');
        $this->commands([
            ReconcileLibraryCommand::class,
            VerifyLibraryChecksumsCommand::class,
        ]);
    }

    public function boot(Schedule $schedule): void
    {
        $schedule
            ->command('library:reconcile --delete-orphans')
            ->dailyAt('02:15')
            ->onOneServer()
            ->runInBackground()
            ->withoutOverlapping();
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Master\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Lahatre\Master\Console\Commands\PruneExpiredNotesCommand;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Services\MasterService;
use Lahatre\Master\Support\UnitCache;

class MasterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(UnitCache::class);
        $this->app->scoped(MasterInterface::class, MasterService::class);
        $this->commands([
            PruneExpiredNotesCommand::class,
        ]);
    }

    public function boot(Schedule $schedule): void
    {
        $schedule
            ->command('master:notes:prune')
            ->dailyAt('02:00')
            ->withoutOverlapping();
    }
}

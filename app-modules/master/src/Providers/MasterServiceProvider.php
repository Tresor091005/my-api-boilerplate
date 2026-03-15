<?php

declare(strict_types=1);

namespace Lahatre\Master\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Master\Support\UnitCache;
use Lahatre\Shared\Registries\MorphMapRegistry;

class MasterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(UnitCache::class);
    }

    public function boot(MorphMapRegistry $registry): void
    {
        $registry->register([
            'currency'   => Currency::class,
            'unit'       => Unit::class,
            'unit_group' => UnitGroup::class,
        ]);
    }
}

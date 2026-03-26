<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Services\InventoryService;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/inventory.php', 'inventory');

        $this->app->scoped(InventoryInterface::class, InventoryService::class);
    }

    public function boot(): void {}
}

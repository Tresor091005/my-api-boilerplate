<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Services\InventoryService;
use Lahatre\Shared\Registries\MorphMapRegistry;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/inventory.php', 'inventory');

        $this->app->scoped(InventoryInterface::class, InventoryService::class);
    }

    public function boot(MorphMapRegistry $registry): void
    {
        $registry->register([
            'inventory_item'     => InventoryItem::class,
            'inventory_location' => InventoryLocation::class,
        ]);
    }
}

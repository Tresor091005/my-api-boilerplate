<?php

declare(strict_types=1);

namespace Lahatre\Service\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Service\Contracts\ServiceInterface;
use Lahatre\Service\Services\ServiceService;

class ServiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ServiceInterface::class, ServiceService::class);
    }
}

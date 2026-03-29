<?php

declare(strict_types=1);

namespace Lahatre\Organization\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Organization\Services\OrganizationService;

class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrganizationInterface::class, OrganizationService::class);
    }

    public function boot(): void {}
}

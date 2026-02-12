<?php

declare(strict_types=1);

namespace Lahatre\Iam\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Iam\Services\AuthContext;

class IamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AuthContext::class);
    }

    public function boot(): void {}
}

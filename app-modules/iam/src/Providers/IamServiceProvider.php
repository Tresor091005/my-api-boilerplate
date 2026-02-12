<?php

declare(strict_types=1);

namespace Lahatre\Iam\Providers;

use Illuminate\Support\ServiceProvider;
use Lahatre\Iam\Auth\AuthContext;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class IamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AuthContext::class);
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}

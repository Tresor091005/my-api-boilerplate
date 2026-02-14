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

        $this->mergeConfigFrom(__DIR__.'/../../config/permission.php', 'permission');
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        /*
        TODO use Illuminate\Auth\Access\Response::allow, deny and denyAsNotFound
        Gate::authorize('update', [$post, $request->category]); when multiple element
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        after direct actions on permissions or roles table with using given methods
        use enum and lang for builtin roles
        */
    }
}

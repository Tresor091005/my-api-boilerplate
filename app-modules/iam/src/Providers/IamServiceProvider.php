<?php

declare(strict_types=1);

namespace Lahatre\Iam\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Lahatre\Iam\Auth\AuthContext;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Http\Middleware\EnsurePlatformAccess;
use Lahatre\Iam\Http\Middleware\EnsureProfileComplete;
use Lahatre\Iam\Http\Middleware\ResolveAuthContext;
use Lahatre\Iam\Http\Middleware\SetTeamPermissionsId;
use Lahatre\Iam\Http\Middleware\TrackSessionActivity;
use Lahatre\Iam\Integrations\GeoipDatabaseUpdater;
use Laravel\Sanctum\Sanctum;

class IamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AuthContext::class);

        $this->mergeConfigFrom(__DIR__.'/../../config/permission.php', 'permission');
        $this->mergeConfigFrom(__DIR__.'/../../config/system-permissions.php', 'iam.system_permissions');
    }

    public function boot(Schedule $schedule, Router $router, HttpKernelContract $httpKernel): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        $this->registerMiddleware($router, $httpKernel);

        Gate::define('operate-platform', fn (Authenticatable $user): bool => authContext()->hasPlatformAccess()
            && authContext()->user()?->getAuthIdentifier() === $user->getAuthIdentifier());

        $schedule
            ->command('iam:geoip-update')
            ->dailyAt('03:00')
            ->when(fn (): bool => app(GeoipDatabaseUpdater::class)->isConfigured())
            ->runInBackground();

        $schedule
            ->command('sanctum:prune-expired --hours=24')
            ->dailyAt('02:30')
            ->onOneServer()
            ->runInBackground()
            ->withoutOverlapping();

        $schedule
            ->call(fn (): int => DB::table('iam_organization_registration_tokens')->where('expires_at', '<=', now())->delete())
            ->name('prune-organization-registration-tokens')
            ->dailyAt('02:35')
            ->onOneServer()
            ->withoutOverlapping();

        $schedule
            ->call(fn (): int => DB::table('iam_email_login_challenges')->where('expires_at', '<=', now())->delete())
            ->name('prune-email-login-challenges')
            ->dailyAt('02:40')
            ->onOneServer()
            ->withoutOverlapping();

        $schedule
            ->call(fn (): int => DB::table('iam_google_auth_challenges')->where('expires_at', '<=', now())->delete())
            ->name('prune-google-auth-challenges')
            ->dailyAt('02:45')
            ->onOneServer()
            ->withoutOverlapping();

        /*
        TODO use Illuminate\Auth\Access\Response::allow, deny and denyAsNotFound
        Gate::authorize('update', [$post, $request->category]); when multiple element
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        after direct actions on permissions or roles table with using given methods
        */
    }

    /** The organization context must be resolved before route bindings, so bound models are looked up inside it. */
    private function registerMiddleware(Router $router, HttpKernelContract $httpKernel): void
    {
        $router->aliasMiddleware('iam.profile-complete', EnsureProfileComplete::class);
        $router->aliasMiddleware('iam.session-activity', TrackSessionActivity::class);
        $router->aliasMiddleware('iam.organization-context', ResolveAuthContext::class);
        $router->aliasMiddleware('iam.user-context', ResolveAuthContext::class.':user');
        $router->aliasMiddleware('iam.organization-permissions', SetTeamPermissionsId::class);
        $router->aliasMiddleware('iam.platform-access', EnsurePlatformAccess::class);

        if ($httpKernel instanceof HttpKernel) {
            $httpKernel->addToMiddlewarePriorityBefore(SubstituteBindings::class, ResolveAuthContext::class);
            $httpKernel->addToMiddlewarePriorityBefore(SubstituteBindings::class, SetTeamPermissionsId::class);
            $httpKernel->addToMiddlewarePriorityBefore(SubstituteBindings::class, EnsurePlatformAccess::class);
            $httpKernel->addToMiddlewarePriorityBefore(ResolveAuthContext::class, TrackSessionActivity::class);
            $httpKernel->addToMiddlewarePriorityBefore(TrackSessionActivity::class, EnsureProfileComplete::class);
        }
    }
}

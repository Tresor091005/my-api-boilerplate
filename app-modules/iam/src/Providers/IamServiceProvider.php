<?php

declare(strict_types=1);

namespace Lahatre\Iam\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Lahatre\Iam\Auth\AuthContext;
use Lahatre\Iam\Auth\PersonalAccessToken;
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

    public function boot(Schedule $schedule): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

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

        /*
        TODO use Illuminate\Auth\Access\Response::allow, deny and denyAsNotFound
        Gate::authorize('update', [$post, $request->category]); when multiple element
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        after direct actions on permissions or roles table with using given methods
        use enum and lang for builtin roles
        */
    }
}

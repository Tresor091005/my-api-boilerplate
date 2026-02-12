<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Sanctum\PersonalAccessToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(!app()->isProduction());

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}

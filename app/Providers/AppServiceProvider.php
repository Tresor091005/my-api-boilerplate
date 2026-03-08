<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

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

        $this->registerStrMacros();

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'Sanctum')
                );
            });
    }

    private function registerStrMacros(): void
    {
        Str::macro('sanitize', fn (string $value): string => (string) str($value)->trim()->squish());
        Stringable::macro('sanitize', function (): Stringable {
            /** @var Stringable $this */
            return $this->trim()->squish();
        });

        Str::macro('normalize', fn (string $value): string => (string) str($value)->sanitize()->lower());
        Stringable::macro('normalize', function (): Stringable {
            /** @var Stringable $this */
            return $this->sanitize()->lower();
        });

        Str::macro('toUpper', fn (string $value): string => (string) str($value)->normalize()->upper());
        Stringable::macro('toUpper', function (): Stringable {
            /** @var Stringable $this */
            return $this->normalize()->upper();
        });

        Str::macro('toTitle', fn (string $value): string => (string) str($value)->normalize()->title());
        Stringable::macro('toTitle', function (): Stringable {
            /** @var Stringable $this */
            return $this->normalize()->title();
        });

        Str::macro('toHeadline', fn (string $value): string => (string) str($value)->normalize()->headline());
        Stringable::macro('toHeadline', function (): Stringable {
            /** @var Stringable $this */
            return $this->normalize()->headline();
        });

        Str::macro('toKebab', fn (string $value): string => (string) str($value)->normalize()->kebab());
        Stringable::macro('toKebab', function (): Stringable {
            /** @var Stringable $this */
            return $this->normalize()->kebab();
        });
    }
}

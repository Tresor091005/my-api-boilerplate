<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Career\Job;
use App\Models\Company\CompanyMember;
use App\Models\Tag;
use App\Models\User\User;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Lahatre\Shared\Registries\MorphMapRegistry;

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
        $this->configureEloquent();
        $this->configureFactories();
        $this->configureScramble();

        $this->registerStrMacros();
    }

    /**
     * Configure Eloquent global settings.
     */
    private function configureEloquent(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(!app()->isProduction());
        Relation::requireMorphMap(true);
    }

    /**
     * Configure automatic factory and model discovery.
     */
    private function configureFactories(): void
    {
        Factory::guessFactoryNamesUsing(fn (string $modelName) => str($modelName)
            ->when(
                str($modelName)->startsWith('App\\Models\\'),
                fn ($str) => $str->replace('App\\Models\\', 'Database\\Factories\\'),
                fn ($str) => $str->replace('Models\\', 'Database\\Factories\\')
            )
            ->append('Factory')
            ->toString());

        Factory::guessModelNamesUsing(function (Factory $factory) {
            $factoryClass = $factory::class;

            return str($factoryClass)
                ->when(
                    str($factoryClass)->startsWith('Database\\Factories\\'),
                    fn ($str) => $str->replace('Database\\Factories\\', 'App\\Models\\'),
                    fn ($str) => $str->replace('Database\\Factories\\', 'Models\\')
                )
                ->beforeLast('Factory')
                ->toString();
        });
    }

    /**
     * Configure Scramble API documentation.
     */
    private function configureScramble(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'Sanctum')
                );
            });
    }

    /**
     * Register custom string macros.
     */
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

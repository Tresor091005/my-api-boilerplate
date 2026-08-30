<?php

declare(strict_types=1);

namespace App\Providers;

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
        $this->configureIdeHelper();
        $this->configureEloquent();
        $this->configureFactories();
        $this->configureScramble();

        $this->registerStrMacros();
    }

    /**
     * Provide a technical organization context while ide-helper inspects relations.
     */
    private function configureIdeHelper(): void
    {
        if (app()->runningConsoleCommand('ide-helper:models')) {
            setPermissionsTeamId('ide-helper');
        }
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
        // sanitize: trims leading/trailing whitespace and squishes internal whitespace; "  Hello   WORLD  " => "Hello WORLD".
        Str::macro('sanitize', fn (string $value): string => (string) str($value)->trim()->squish());
        Stringable::macro('sanitize', function (): Stringable {
            /** @var Stringable $this */
            return $this->trim()->squish();
        });

        // normalize: applies sanitize, then lowercases the value; "  Hello   WORLD  " => "hello world".
        Str::macro('normalize', fn (string $value): string => (string) str($value)->sanitize()->lower());
        Stringable::macro('normalize', function (): Stringable {
            /** @var Stringable $this */
            return $this->sanitize()->lower();
        });

        // toUpper: applies normalize, then uppercases the value; "  hello   world  " => "HELLO WORLD".
        Str::macro('toUpper', fn (string $value): string => (string) str($value)->normalize()->upper());
        Stringable::macro('toUpper', function (): Stringable {
            /** @var Stringable $this */
            return $this->normalize()->upper();
        });

        // toTitle: applies normalize, then converts each word to title case while preserving separators; "  hello_world  " => "Hello_world".
        Str::macro('toTitle', fn (string $value): string => (string) str($value)->normalize()->title());
        Stringable::macro('toTitle', function (): Stringable {
            /** @var Stringable $this */
            return $this->normalize()->title();
        });

        // toHeadline: applies normalize, then converts separators such as '_' and '-' to spaces; "  hello_world  " => "Hello World".
        Str::macro('toHeadline', fn (string $value): string => (string) str($value)->normalize()->headline());
        Stringable::macro('toHeadline', function (): Stringable {
            /** @var Stringable $this */
            return $this->normalize()->headline();
        });

        // toKebab: applies normalize, then converts the text to kebab-case; "  Hello   WORLD  " => "hello-world".
        Str::macro('toKebab', fn (string $value): string => (string) str($value)->normalize()->kebab());
        Stringable::macro('toKebab', function (): Stringable {
            /** @var Stringable $this */
            return $this->normalize()->kebab();
        });
    }
}

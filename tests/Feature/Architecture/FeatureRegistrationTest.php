<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Lang;
use Lahatre\Billing\Contracts\ProvidesFeatures;
use Lahatre\Billing\Registries\FeatureDefinition;
use Lahatre\Billing\Registries\FeatureRegistry;
use Symfony\Component\Finder\Finder;

it('registers every module class that provides billing features', function (): void {
    $registeredKeys = array_keys(app(FeatureRegistry::class)->all());
    $finder = (new Finder)->files()->in(base_path('app-modules'))->path('#/src/#')->name('*.php');
    $providers = 0;
    $failures = [];

    foreach ($finder as $file) {
        $className = resolveFullyQualifiedClassName($file);

        if ($className === null || !is_subclass_of($className, ProvidesFeatures::class)) {
            continue;
        }

        $providers++;
        $declaredKeys = array_map(
            fn (FeatureDefinition $definition): string => $definition->key,
            app($className)->definitions(),
        );
        $missingKeys = array_diff($declaredKeys, $registeredKeys);

        if ($missingKeys !== []) {
            $failures[] = "{$className} is not registered in its module provider: ".implode(', ', $missingKeys);
        }
    }

    expect($providers)->toBeGreaterThan(0)
        ->and($failures)->toBe([]);
});

it('names every registered feature in its module translations', function (): void {
    $definitions = app(FeatureRegistry::class)->all();
    $untranslatedKeys = array_keys(array_filter(
        $definitions,
        fn (FeatureDefinition $definition): bool => !Lang::has($definition->translationKey(), 'en', false),
    ));

    expect(count($definitions))->toBeGreaterThan(0)
        ->and($untranslatedKeys)->toBe([]);
});

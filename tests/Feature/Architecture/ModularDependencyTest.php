<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Get all available module names.
 *
 * @return array<int, string>
 */
function getModuleNames(): array
{
    $modulesDir = base_path('app-modules');
    $modules = [];

    if (is_dir($modulesDir)) {
        $finder = new Finder();
        $finder->directories()->in($modulesDir)->depth(0);
        foreach ($finder as $dir) {
            $modules[] = strtolower($dir->getBasename());
        }
    }

    return $modules;
}

/**
 * Find cross-module dependency violations.
 */
function checkModuleDependencies(string $module, array $allModules, array $allowedDependencies): array
{
    $modulePath = base_path("app-modules/{$module}");

    if (!is_dir($modulePath)) {
        return [];
    }

    $prohibitedModules = array_diff($allModules, [$module], $allowedDependencies);
    $prohibitedNamespaces = array_map(fn ($m): string => 'Lahatre\\'.Str::studly($m), $prohibitedModules);

    $finder = new Finder();
    $finder->files()->in($modulePath)->name('*.php');

    $failures = [];

    foreach ($finder as $file) {
        // GLOBAL EXCEPTION: The shared helpers file is allowed to touch everything for convenience
        if ($module === 'shared' && Str::endsWith($file->getRelativePathname(), 'src/helpers.php')) {
            continue;
        }

        // Skip discovery files
        if (Str::contains($file->getRelativePathname(), ['MorphMapRegistry.php', 'ModelFinder.php'])) {
            continue;
        }

        $content = $file->getContents();
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            foreach ($prohibitedNamespaces as $prohibited) {
                // Regex to match the namespace usage strictly
                if (preg_match('/(?<![a-zA-Z0-9_\\\])'.preg_quote($prohibited, '/').'(?![a-zA-Z0-9_])/', $line)) {
                    $lineNumber = $index + 1;
                    $failures[] = "[{$module}] file '{$file->getRelativePathname()}:{$lineNumber}' imports forbidden namespace '{$prohibited}'.";
                }
            }
        }
    }

    return $failures;
}

it('enforces modular architecture and prohibits cross-dependencies', function (): void {
    $modules = getModuleNames();

    /**
     * Define the dependency graph here.
     * Each module can ONLY depend on the modules listed in its array.
     */
    $dependencyMap = [
        'shared'       => [],
        'master'       => ['shared'],
        'inventory'    => ['shared', 'master'],
        'organization' => ['shared', 'master'],
        'iam'          => ['shared', 'master', 'organization'],
        'catalog'      => ['shared', 'master', 'inventory'],
    ];

    $failures = [];

    foreach ($modules as $module) {
        $allowed = $dependencyMap[$module] ?? [];
        $moduleFailures = checkModuleDependencies($module, $modules, $allowed);
        $failures = array_merge($failures, $moduleFailures);
    }

    if ($failures !== []) {
        $this->fail("Modular Dependency Failures (Cross-module imports detected):\n\n".implode("\n", array_unique($failures)));
    }

    expect(true)->toBeTrue();
});

it('ensures modules do not depend on the main App namespace (except Models)', function (): void {
    $modules = getModuleNames();
    $failures = [];

    foreach ($modules as $module) {
        $modulePath = base_path("app-modules/{$module}");
        if (!is_dir($modulePath)) {
            continue;
        }

        $finder = new Finder();
        $finder->files()->in($modulePath)->name('*.php');

        foreach ($finder as $file) {
            if (Str::contains($file->getRelativePathname(), ['MorphMapRegistry.php', 'ModelFinder.php'])) {
                continue;
            }

            $content = $file->getContents();
            $lines = explode("\n", $content);

            foreach ($lines as $index => $line) {
                if (Str::contains($line, 'App\\') && !Str::contains($line, 'App\\Models\\')) {
                    $lineNumber = $index + 1;
                    $failures[] = "[{$module}] file '{$file->getRelativePathname()}:{$lineNumber}' imports core 'App' namespace (outside Models).";
                }
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Core Dependency Failures (Modules should not depend on main App logic):\n\n".implode("\n", array_unique($failures)));
    }

    expect(true)->toBeTrue();
});

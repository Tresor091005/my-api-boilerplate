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
    $moduleNamespace = 'Lahatre\\'.Str::studly($module);
    $modulePath = base_path("app-modules/{$module}/src");

    if (!is_dir($modulePath)) {
        return [];
    }

    $prohibitedModules = array_diff($allModules, [$module], $allowedDependencies);
    $prohibitedNamespaces = array_map(fn ($m): string => 'Lahatre\\'.Str::studly($m), $prohibitedModules);

    $finder = new Finder();
    $finder->files()->in($modulePath)->name('*.php');

    $failures = [];

    foreach ($finder as $file) {
        // Skip files that naturally need to touch many namespaces for discovery
        if (Str::contains($file->getRelativePathname(), ['MorphMapRegistry.php', 'ModelFinder.php'])) {
            continue;
        }

        $content = $file->getContents();
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            // Match "Lahatre\..." but avoid false positives from strings/comments
            // We only care if it's a real usage: use, new, extends, implements, static call, or type hint
            if (!preg_match('/^\s*(use|new|extends|implements)\s+Lahatre\\\\|(\(|,)\s*Lahatre\\\\|\\\\Lahatre\\\\|::class/', $line)) {
                continue;
            }

            foreach ($prohibitedNamespaces as $prohibited) {
                if (Str::contains($line, $prohibited)) {
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

    // Configuration of allowed dependencies
    // 'shared' and 'master' are considered hub modules that others can use.
    $commonHubs = ['shared', 'master'];

    $failures = [];

    foreach ($modules as $module) {
        // Skip shared itself from dependency check on hubs, or define rules
        if ($module === 'shared') {
            // shared should ideally depend on NOTHING other than Laravel/Vendor
            $allowed = [];
        } elseif ($module === 'master') {
            // master can use shared
            $allowed = ['shared'];
        } else {
            // All other modules (catalog, inventory, iam) can use hubs
            $allowed = $commonHubs;
        }

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
        $modulePath = base_path("app-modules/{$module}/src");
        if (!is_dir($modulePath)) {
            continue;
        }

        $finder = new Finder();
        $finder->files()->in($modulePath)->name('*.php');

        foreach ($finder as $file) {
            // Skip files that naturally need to touch many namespaces for discovery
            if (Str::contains($file->getRelativePathname(), ['MorphMapRegistry.php', 'ModelFinder.php'])) {
                continue;
            }

            $content = $file->getContents();
            $lines = explode("\n", $content);

            foreach ($lines as $index => $line) {
                // If imports "App\..." but NOT "App\Models\..."
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

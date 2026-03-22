<?php

declare(strict_types=1);

namespace Lahatre\Shared\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class ModelFinder
{
    /**
     * Get all application and module models.
     *
     * @return array<int, string>
     */
    public static function getAllModels(): array
    {
        $models = [];
        $scanList = config('model-integrity.extra_namespaces', []);
        $modulesDir = base_path(config('app-modules.modules_directory', 'app-modules'));
        $modulesNamespace = config('app-modules.modules_namespace', 'Lahatre');

        if (is_dir($modulesDir)) {
            $finder = new Finder();
            $finder->directories()->in($modulesDir)->depth(0);
            foreach ($finder as $dir) {
                $scanList[] = $modulesNamespace . '\\' . Str::studly($dir->getBasename()) . '\\Models';
            }
        }

        foreach ($scanList as $namespace) {
            $path = match (true) {
                Str::startsWith($namespace, 'App\\')                => base_path(str_replace(['App\\', '\\'], ['app/', '/'], $namespace)),
                Str::startsWith($namespace, $modulesNamespace . '\\') => (function () use ($namespace): string {
                    $parts = explode('\\', $namespace);
                    $module = strtolower($parts[1]);
                    $subPath = implode('/', array_slice($parts, 2));

                    return base_path(config('app-modules.modules_directory', 'app-modules') . "/{$module}/src/{$subPath}");
                })(),
                default => null,
            };

            if (!$path || !is_dir($path)) {
                continue;
            }

            $finder = new Finder();
            $finder->files()->in($path)->name('*.php');
            foreach ($finder as $file) {
                $relativePath = $file->getRelativePathname();
                $class = $namespace . '\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);

                if (!class_exists($class)) {
                    continue;
                }
                $reflection = new ReflectionClass($class);
                if ($reflection->isSubclassOf(Model::class) && !$reflection->isAbstract()) {
                    $models[] = $class;
                }
            }
        }

        return $models;
    }
}

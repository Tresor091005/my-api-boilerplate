<?php

declare(strict_types=1);

namespace Lahatre\Shared\Registries;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lahatre\Shared\Support\ModelFinder;

class MorphMapRegistry
{
    /** @var array<string, string> */
    protected array $map = [];

    /**
     * MorphMapRegistry constructor.
     */
    public function __construct()
    {
        if (!$this->loadFromCache()) {
            $this->discoverAndRegister();
        }
    }

    /**
     * Register a map of morph aliases to class names.
     *
     * @param  array<string, string>  $morphs
     *
     * @throws InvalidArgumentException
     */
    public function register(array $morphs): void
    {
        foreach ($morphs as $alias => $class) {
            if (isset($this->map[$alias]) && $this->map[$alias] !== $class) {
                throw new InvalidArgumentException(
                    "Morph alias collision: '{$alias}' is already registered for '{$this->map[$alias]}'. " .
                    "Cannot reassign it to '{$class}'. Please provide a unique alias."
                );
            }
            $this->map[$alias] = $class;
        }

        Relation::morphMap($this->map);
    }

    /**
     * Get the current registered map.
     *
     * @return array<string, string>
     */
    public function getMap(): array
    {
        return $this->map;
    }

    /**
     * Cache the morph map by discovering all models.
     */
    public function cache(): void
    {
        $this->discoverAndRegister();

        $cachePath = App::bootstrapPath('cache/morph-map.php');
        $content = '<?php return ' . var_export($this->map, true) . ';' . PHP_EOL;

        File::put($cachePath, $content);
    }

    /**
     * Clear the cached morph map.
     */
    public function clear(): void
    {
        $cachePath = App::bootstrapPath('cache/morph-map.php');

        if (File::exists($cachePath)) {
            File::delete($cachePath);
        }

        $this->map = [];
        Relation::morphMap([], false);
    }

    /**
     * Discover all models and register them with smart aliases.
     */
    protected function discoverAndRegister(): void
    {
        $models = ModelFinder::getAllModels();
        $modulesNamespace = config('app-modules.modules_namespace', 'Lahatre');
        $newMap = [];

        foreach ($models as $class) {
            $alias = $this->generateAlias($class, $modulesNamespace);

            if (isset($newMap[$alias]) && $newMap[$alias] !== $class) {
                throw new InvalidArgumentException(
                    "Automatic morph alias collision detected: '{$alias}' is used by both '{$newMap[$alias]}' and '{$class}'. " .
                    "Please manually register one of them in a ServiceProvider with a custom alias."
                );
            }

            $newMap[$alias] = $class;
        }

        $this->register($newMap);
    }

    /**
     * Generate a smart alias for a model class.
     */
    protected function generateAlias(string $class, string $modulesNamespace): string
    {
        $parts = explode('\\', $class);

        // If it's a module model (e.g., Lahatre\Catalog\Models\Product)
        if ($parts[0] === $modulesNamespace && isset($parts[1])) {
            $module = Str::snake($parts[1]);
            $className = Str::snake(end($parts));

            // Avoid redundant prefix if the class name already starts with the module name
            if (Str::startsWith($className, "{$module}_")) {
                return $className;
            }

            return "{$module}_{$className}";
        }

        // Default: use snake_case of the class name
        return Str::snake(end($parts));
    }

    /**
     * Load the map from cache if it exists.
     */
    protected function loadFromCache(): bool
    {
        $cachePath = App::bootstrapPath('cache/morph-map.php');

        if (File::exists($cachePath)) {
            $this->map = require $cachePath;
            Relation::morphMap($this->map);

            return true;
        }

        return false;
    }
}

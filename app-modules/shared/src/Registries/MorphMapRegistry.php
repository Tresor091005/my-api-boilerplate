<?php

declare(strict_types=1);

namespace Lahatre\Shared\Registries;

use Illuminate\Database\Eloquent\Model;
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
     * Load the cached morph map or discover and register all models.
     */
    public function discover(): void
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
                    "Morph alias collision: '{$alias}' is already registered for '{$this->map[$alias]}'. ".
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
     * Resolve the registered morph alias for a model class or instance.
     *
     * @param  class-string<Model>|Model  $model
     */
    public function getAlias(string|Model $model): ?string
    {
        $class = $model instanceof Model ? $model::class : $model;

        return array_search($class, $this->map, true) ?: null;
    }

    /**
     * Resolve a model class from a registered morph alias.
     *
     * @return class-string<Model>|null
     */
    public function getModel(string $alias): ?string
    {
        $model = $this->map[$alias] ?? null;

        return is_string($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    /**
     * Cache the morph map by discovering all models.
     */
    public function cache(): void
    {
        $this->discoverAndRegister();

        $cachePath = App::bootstrapPath('cache/morph-map.php');
        $content = '<?php return '.var_export($this->map, true).';'.PHP_EOL;

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
     * Discover all models and register them with table-based aliases.
     */
    protected function discoverAndRegister(): void
    {
        $models = ModelFinder::getAllModels();
        $newMap = [];

        foreach ($models as $class) {
            $alias = $this->generateAlias($class);

            if (isset($newMap[$alias]) && $newMap[$alias] !== $class) {
                throw new InvalidArgumentException(
                    "Automatic morph alias collision detected: '{$alias}' is used by both '{$newMap[$alias]}' and '{$class}'. ".
                    'Please manually register one of them in a ServiceProvider with a custom alias.'
                );
            }

            $newMap[$alias] = $class;
        }

        $this->register($newMap);
    }

    /**
     * Generate an alias from the model's singular table name.
     */
    protected function generateAlias(string $class): string
    {
        /** @var Model $model */
        $model = new $class;

        return Str::singular($model->getTable());
    }

    /**
     * Load the map from cache if it exists.
     */
    protected function loadFromCache(): bool
    {
        if (config('app.env') !== 'production') {
            return false;
        }

        $cachePath = App::bootstrapPath('cache/morph-map.php');

        if (File::exists($cachePath)) {
            $this->map = require $cachePath;
            Relation::morphMap($this->map);

            return true;
        }

        return false;
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use InterNACHI\Modular\Console\Commands\Make\Modularize;
use Lahatre\Shared\Http\Resources\BaseCollection;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Input\InputOption;

class MakeApiResource extends GeneratorCommand
{
    use Modularize;

    protected $name = 'make:api-resource';

    protected $description = 'Create a new API resource and collection pair';

    protected $type = 'API Resource';

    protected function getStub()
    {
        return dirname(__DIR__, 4).'/stubs/api-resource.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        if ($module = $this->module()) {
            $rootNamespace = rtrim((string) $module->namespaces->first(), '\\');
        }

        return rtrim($rootNamespace, '\\').'\\Http\\Resources';
    }

    protected function getOptions()
    {
        return [
            new InputOption('model', 'm', InputOption::VALUE_OPTIONAL, 'The model that the resource applies to.'),
            new InputOption('force', null, InputOption::VALUE_NONE, 'Create the class even if the resource already exists.'),
        ];
    }

    /**
     * Execute the console command.
     *
     * @throws FileNotFoundException
     *
     * @return bool|null
     */
    public function handle()
    {
        // Generate the Resource
        $resourceName = $this->getNameInput();
        $this->type = 'API Resource'; // Ensure type is correct for resource generation
        $qualifiedResourceName = $this->qualifyClass($resourceName);
        $resourcePath = $this->getPath($qualifiedResourceName);

        if (!$this->option('force') && $this->alreadyExists($qualifiedResourceName)) {
            $this->error($this->type.' already exists!');

            return false;
        }

        $this->makeDirectory($resourcePath);

        $stub = $this->files->get($this->getStub());

        $stub = $this->replaceNamespace($stub, $qualifiedResourceName)->replaceClass($stub, $resourceName);
        $stub = $this->replaceResourceContent($stub, $resourceName);

        $this->files->put($resourcePath, $stub);

        $this->info($this->type.' created successfully.');

        // Generate the Collection
        $collectionName = $this->getCollectionName($resourceName);
        $this->type = 'API Collection'; // Ensure type is correct for collection generation
        $qualifiedCollectionName = $this->qualifyClass($collectionName);
        $collectionPath = $this->getPath($qualifiedCollectionName);

        if (!$this->option('force') && $this->alreadyExists($qualifiedCollectionName)) {
            $this->error($this->type.' already exists!');

            return false;
        }

        $this->makeDirectory($collectionPath);

        $collectionStub = $this->files->get($this->getCollectionStub());

        $collectionStub = $this->replaceNamespace($collectionStub, $qualifiedCollectionName)->replaceClass($collectionStub, $collectionName);
        $collectionStub = $this->replaceCollectionContent($collectionStub, $resourceName);

        $this->files->put($collectionPath, $collectionStub);

        $this->info($this->type.' created successfully.');

        return true;
    }

    protected function getCollectionStub(): string
    {
        return dirname(__DIR__, 4).'/stubs/api-collection.stub';
    }

    protected function getCollectionName(string $resourceName): string
    {
        return Str::beforeLast($resourceName, 'Resource').'Collection';
    }

    protected function replaceResourceContent(string $stub, string $name): string
    {
        $model = $this->option('model');
        if ($model) {
            $namespacedModel = $this->qualifyModel($model);
            $mixinPlaceholder = "/**\n * @mixin \\{$namespacedModel}\n */\n";
            $stub = str_replace('{{ mixinPlaceholder }}', $mixinPlaceholder, $stub);

            try {
                $reflection = new ReflectionClass($namespacedModel);
                $casts = $this->getModelCasts($reflection);
                $toArrayContent = $this->generateToArrayContent($casts);
            } catch (ReflectionException) {
                $this->warn('Could not reflect model: '.$namespacedModel.'. Using parent::toArray() fallback.');
                $toArrayContent = '            return parent::toArray($request);';
            } catch (\Throwable $e) {
                $this->warn('An error occurred while processing model casts. Using parent::toArray() fallback. Error: '.$e->getMessage());
                $toArrayContent = '            return parent::toArray($request);';
            }
        } else {
            $mixinPlaceholder = ''; // No mixin if no model
            $stub = str_replace('{{ mixinPlaceholder }}', $mixinPlaceholder, $stub);
            $toArrayContent = '            return parent::toArray($request);';
        }

        $stub = str_replace('{{ toArrayContent }}', $toArrayContent, $stub);

        return $stub;
    }

    protected function replaceCollectionContent(string $stub, string $resourceName): string
    {
        $resourceClass = class_basename($this->qualifyClass($resourceName));
        $stub = str_replace('{{ resourceClass }}', $resourceClass, $stub);
        $stub = str_replace('{{ namespacedResourceClass }}', $this->qualifyClass($resourceName), $stub); // Needs full namespace for use statement

        $stub = str_replace('{{ namespacedBaseCollection }}', BaseCollection::class, $stub);

        return $stub;
    }

    protected function getModelCasts(ReflectionClass $reflection): array
    {
        $casts = [];
        // Attempt to get from $casts property
        if ($reflection->hasProperty('casts')) {
            $property = $reflection->getProperty('casts');
            // newInstanceWithoutConstructor is important to avoid running model's constructor logic
            $casts = $property->getValue($reflection->newInstanceWithoutConstructor());
        }

        // If $casts property is empty, try to get from casts() method (Laravel 12+)
        // This is more complex as it involves calling a method that might have dependencies.
        // For simplicity and avoiding complex dependency resolution in a command,
        // we'll stick to the $casts property as the primary source for now.
        // If the user's models primarily use the casts() method, this might need refinement.

        return $casts;
    }

    protected function generateToArrayContent(array $casts): string
    {
        if ($casts === []) {
            return '            return parent::toArray($request);';
        }

        $content = [];
        foreach (array_keys($casts) as $key) {
            $content[] = "            '{$key}' => \$this->{$key},";
        }

        return '        return [
'.implode('
', $content).'
        ];';
    }

    protected function qualifyModel(string $model): string
    {
        $model = ltrim($model, '\\/');
        $model = str_replace('/', '\\', $model);

        if ($module = $this->module()) {
            $moduleNamespace = rtrim((string) $module->namespaces->first(), '\\');
            $modelClass = $moduleNamespace.'\\Models\\'.$model;
            if (class_exists($modelClass)) {
                return $modelClass;
            }
        }

        $rootNamespace = $this->laravel->getNamespace();
        if (Str::startsWith($model, $rootNamespace)) {
            return $model;
        }

        $model = str_replace('/', '\\', $model);

        return is_dir(app_path('Models'))
                    ? $rootNamespace.'Models\\'.$model
                    : $rootNamespace.$model;
    }
}

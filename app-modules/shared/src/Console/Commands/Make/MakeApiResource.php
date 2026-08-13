<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use InterNACHI\Modularize\ModularizeGeneratorCommand;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Input\InputOption;

class MakeApiResource extends GeneratorCommand
{
    use ModularizeGeneratorCommand;

    protected $name = 'make:api-resource';

    protected $description = 'Create a new API resource and collection pair';

    protected $type = 'API Resource';

    protected function getStub(): string
    {
        return dirname(__DIR__, 4).'/stubs/api-resource.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        if ($module = $this->module()) {
            $rootNamespace = rtrim((string) $module->namespaces->first(), '\\');
        }

        return rtrim($rootNamespace, '\\').'\\Http\\Resources';
    }

    protected function getOptions(): array
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
     */
    public function handle(): ?bool
    {
        $resourceName = $this->getNameInput();
        $qualifiedResourceName = $this->qualifyClass($resourceName);
        $resourcePath = $this->getPath($qualifiedResourceName);
        $collectionName = $this->getCollectionName($resourceName);
        $qualifiedCollectionName = $this->qualifyClass($collectionName);
        $collectionPath = $this->getPath($qualifiedCollectionName);

        if (!$this->option('force')) {
            if ($this->alreadyExists($qualifiedResourceName)) {
                $this->fail(__('shared::console.api_resource.already_exists', ['type' => 'API Resource']));
            }

            if ($this->alreadyExists($qualifiedCollectionName)) {
                $this->fail(__('shared::console.api_resource.already_exists', ['type' => 'API Collection']));
            }
        }

        $stub = $this->files->get($this->getStub());
        $stub = $this->replaceNamespace($stub, $qualifiedResourceName)->replaceClass($stub, $resourceName);
        $stub = $this->replaceResourceContent($stub, $resourceName);

        $this->makeDirectory($resourcePath);
        $this->files->put($resourcePath, $stub);
        $this->makeDirectory($collectionPath);
        $collectionStub = $this->files->get($this->getCollectionStub());
        $collectionStub = $this->replaceNamespace($collectionStub, $qualifiedCollectionName)->replaceClass($collectionStub, $collectionName);
        $collectionStub = $this->replaceCollectionContent($collectionStub, $resourceName);
        $this->files->put($collectionPath, $collectionStub);

        $this->info(__('shared::console.api_resource.created_successfully', ['type' => 'API Resource']));
        $this->info(__('shared::console.api_resource.created_successfully', ['type' => 'API Collection']));

        return null;
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
                $this->warn(__('shared::console.api_resource.reflection_failed', ['model' => $namespacedModel]));
                $toArrayContent = '            return parent::toArray($request);';
            } catch (\Throwable $e) {
                $this->warn(__('shared::console.api_resource.casts_failed', [
                    'model' => $namespacedModel,
                    'error' => $e->getMessage(),
                ]));
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

        return $stub;
    }

    protected function getModelCasts(ReflectionClass $reflection): array
    {
        $casts = [];
        // Attempt to get from $casts property
        if ($reflection->hasProperty('casts')) {
            $property = $reflection->getProperty('casts');
            // newInstanceWithoutConstructor is important to avoid running model's constructor logic
            $value = $property->getValue($reflection->newInstanceWithoutConstructor());
            $casts = is_array($value) ? $value : [];
        }

        // If $casts property is empty, try to get from casts() method (Laravel 13+)
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
        $excludedKeys = [
            'deleted_at',
            'organization_id',
            'password',
            'remember_token',
        ];

        foreach (array_diff(array_keys($casts), $excludedKeys) as $key) {
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

        if (class_exists($model) || str_contains($model, '\\')) {
            return $model;
        }

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

<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use InterNACHI\Modular\Console\Commands\Make\Modularize;
use Symfony\Component\Console\Input\InputOption;

class MakePolicy extends GeneratorCommand
{
    use Modularize;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:policy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new policy class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Policy';

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     *
     * @throws FileNotFoundException
     *
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = $this->files->get($this->getStub());

        $model = $this->option('model');
        if (!$model) {
            $model = Str::singular(str_replace('Policy', '', class_basename($name)));
        }

        $namespacedModel = $this->qualifyModelForPolicy($model);
        $modelName = class_basename($namespacedModel);
        $modelPluralSnake = Str::plural(Str::snake($modelName));

        $stub = str_replace(
            ['{{ namespacedModel }}', '{{ model }}', '{{ modelPluralSnake }}'],
            [$namespacedModel, $modelName, $modelPluralSnake],
            $stub
        );

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    /**
     * Get the fully-qualified model class name.
     */
    protected function qualifyModelForPolicy(string $model): string
    {
        $model = ltrim($model, '\\/');
        $model = str_replace('/', '\\', $model);

        if ($module = $this->module()) {
            $moduleNamespace = rtrim((string) $module->namespaces->first(), '\\');
            $modelClass = $moduleNamespace.'\\Models\\'.$model;

            return $modelClass;
        }

        $rootNamespace = $this->laravel->getNamespace();
        if (Str::startsWith($model, $rootNamespace)) {
            return $model;
        }

        return $rootNamespace.'Models\\'.$model;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return dirname(__DIR__, 4).'/stubs/policy.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        if ($module = $this->module()) {
            $rootNamespace = rtrim((string) $module->namespaces->first(), '\\');
        }

        return rtrim($rootNamespace, '\\').'\\Policies';
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the policy already exists.'],
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'The model that the policy applies to.'],
            ['guard', 'g', InputOption::VALUE_OPTIONAL, 'The guard that the policy applies to.'],
        ];
    }
}

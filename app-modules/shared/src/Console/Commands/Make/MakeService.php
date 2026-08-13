<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Console\GeneratorCommand;
use InterNACHI\Modularize\ModularizeGeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeService extends GeneratorCommand
{
    use ModularizeGeneratorCommand;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new service class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Service';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return dirname(__DIR__, 4).'/stubs/service.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        if ($module = $this->module()) {
            $rootNamespace = rtrim((string) $module->namespaces->first(), '\\');
        }

        return rtrim($rootNamespace, '\\').'\\Services';
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the service already exists.'],
        ];
    }
}

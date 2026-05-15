<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Console\GeneratorCommand;
use InterNACHI\Modularize\ModularizeGeneratorCommand;

class MakeDTO extends GeneratorCommand
{
    use ModularizeGeneratorCommand;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:dto';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new DTO class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'DTO';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return dirname(__DIR__, 4).'/stubs/dto.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    #[\Override]
    protected function getDefaultNamespace($rootNamespace)
    {
        if ($module = $this->module()) {
            $rootNamespace = rtrim((string) $module->namespaces->first(), '\\');
        }

        return rtrim($rootNamespace, '\\').'\\DTO';
    }
}

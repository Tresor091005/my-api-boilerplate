<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Foundation\Console\JobMiddlewareMakeCommand;
use InterNACHI\Modularize\ModularizeGeneratorCommand;

final class MakeJobMiddleware extends JobMiddlewareMakeCommand
{
    use ModularizeGeneratorCommand;

    protected function getDefaultNamespace($rootNamespace): string
    {
        if ($module = $this->module()) {
            return rtrim((string) $module->namespaces->first(), '\\').'\\Jobs\\Middleware';
        }

        return parent::getDefaultNamespace($rootNamespace);
    }
}

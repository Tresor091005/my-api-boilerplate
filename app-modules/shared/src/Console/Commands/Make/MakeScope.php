<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Foundation\Console\ScopeMakeCommand;
use InterNACHI\Modularize\ModularizeGeneratorCommand;

final class MakeScope extends ScopeMakeCommand
{
    use ModularizeGeneratorCommand;

    protected function getDefaultNamespace($rootNamespace): string
    {
        if ($module = $this->module()) {
            return rtrim((string) $module->namespaces->first(), '\\').'\\Models\\Scopes';
        }

        return parent::getDefaultNamespace($rootNamespace);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Foundation\Console\InterfaceMakeCommand;
use InterNACHI\Modularize\ModularizeGeneratorCommand;

final class MakeInterface extends InterfaceMakeCommand
{
    use ModularizeGeneratorCommand;

    protected function getDefaultNamespace($rootNamespace): string
    {
        if ($module = $this->module()) {
            return rtrim((string) $module->namespaces->first(), '\\').'\\Contracts';
        }

        return parent::getDefaultNamespace($rootNamespace);
    }
}

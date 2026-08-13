<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Foundation\Console\EnumMakeCommand;
use InterNACHI\Modularize\ModularizeGeneratorCommand;

final class MakeEnum extends EnumMakeCommand
{
    use ModularizeGeneratorCommand;

    protected function getDefaultNamespace($rootNamespace): string
    {
        if ($module = $this->module()) {
            return rtrim((string) $module->namespaces->first(), '\\').'\\Enums';
        }

        return parent::getDefaultNamespace($rootNamespace);
    }
}

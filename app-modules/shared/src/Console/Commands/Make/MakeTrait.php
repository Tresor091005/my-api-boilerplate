<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Foundation\Console\TraitMakeCommand;
use InterNACHI\Modularize\ModularizeGeneratorCommand;

final class MakeTrait extends TraitMakeCommand
{
    use ModularizeGeneratorCommand;

    protected function getDefaultNamespace($rootNamespace): string
    {
        if ($module = $this->module()) {
            return rtrim((string) $module->namespaces->first(), '\\').'\\Traits';
        }

        return parent::getDefaultNamespace($rootNamespace);
    }
}

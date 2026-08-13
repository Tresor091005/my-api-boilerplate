<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Foundation\Console\ViewMakeCommand;
use InterNACHI\Modularize\ModularizeGeneratorCommand;

final class MakeView extends ViewMakeCommand
{
    use ModularizeGeneratorCommand;

    protected function getPath($name): string
    {
        if ($module = $this->module()) {
            return $module->path('resources/views').'/'.$this->getNameInput().'.'.$this->option('extension');
        }

        return parent::getPath($name);
    }
}

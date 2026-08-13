<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Foundation\Console\TestMakeCommand;
use Illuminate\Support\Str;
use InterNACHI\Modularize\ModularizeGeneratorCommand;

final class MakeTest extends TestMakeCommand
{
    use ModularizeGeneratorCommand;

    protected function getPath($name): string
    {
        if ($module = $this->module()) {
            $moduleTestNamespace = rtrim((string) $module->namespaces->first(), '\\').'\\Tests';
            $relativeName = Str::replaceFirst($moduleTestNamespace, '', $name);
            $relativeName = trim($relativeName, '\\/');

            return $module->path('tests').'/'.str_replace('\\', '/', $relativeName).'.php';
        }

        return parent::getPath($name);
    }

    protected function rootNamespace(): string
    {
        if ($module = $this->module()) {
            return rtrim((string) $module->namespaces->first(), '\\').'\\Tests';
        }

        return parent::rootNamespace();
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        if ($module = $this->module()) {
            $rootNamespace = rtrim((string) $module->namespaces->first(), '\\').'\\Tests';
        }

        return parent::getDefaultNamespace($rootNamespace);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Support\Str;
use InterNACHI\Modular\Console\Commands\Make\MakeModel as ModularMakeModel;

final class MakeModel extends ModularMakeModel
{
    protected function handleTestCreation($path): bool
    {
        if (!$this->option('test') && !$this->option('pest') && !$this->option('phpunit')) {
            return false;
        }

        $name = pathinfo($path, PATHINFO_FILENAME).'Test';

        return $this->call('make:test', [
            'name'      => $name,
            '--pest'    => $this->option('pest'),
            '--phpunit' => $this->option('phpunit'),
            '--force'   => $this->option('force'),
        ]) === 0;
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);
        $model = class_basename($this->argument('name'));
        $table = Str::snake(Str::pluralStudly($model));

        if ($module = $this->module()) {
            $table = Str::snake($module->name).'_'.$table;
        }

        return str_replace('{{ table }}', $table, $stub);
    }

    protected function createMigration(): void
    {
        $table = Str::snake(Str::pluralStudly(class_basename($this->argument('name'))));

        if ($this->option('pivot')) {
            $table = Str::singular($table);
        }

        if ($module = $this->module()) {
            $table = Str::snake($module->name).'_'.$table;
        }

        $this->call('make:migration', [
            'name'     => "create_{$table}_table",
            '--create' => $table,
            '--module' => $this->option('module'),
        ]);
    }
}

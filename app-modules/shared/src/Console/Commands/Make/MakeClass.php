<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands\Make;

use Illuminate\Foundation\Console\ClassMakeCommand;
use InterNACHI\Modularize\ModularizeGeneratorCommand;

final class MakeClass extends ClassMakeCommand
{
    use ModularizeGeneratorCommand;
}

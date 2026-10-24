<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Console;

use Illuminate\Console\Command;
use Lahatre\ResponseSelection\Declaration\ResponseDeclarationRegistry;

final class ResponseDeclarationClearCommand extends Command
{
    protected $signature = 'response-declarations:clear';

    protected $description = 'Remove the response declaration cache file';

    public function handle(ResponseDeclarationRegistry $registry): int
    {
        $registry->clear();

        $this->components->info(__('response-selection::console.cleared'));

        return self::SUCCESS;
    }
}

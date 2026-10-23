<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Console;

use Illuminate\Console\Command;
use Lahatre\ResponseSelection\ResponseDeclarationRegistry;

final class ResponseDeclarationCacheCommand extends Command
{
    protected $signature = 'response-declarations:cache';

    protected $description = 'Create the response declaration cache file';

    public function handle(ResponseDeclarationRegistry $registry): int
    {
        $registry->cache();

        $this->components->info(__('shared::console.response_declarations.cached'));

        return self::SUCCESS;
    }
}

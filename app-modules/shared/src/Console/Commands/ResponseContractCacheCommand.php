<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands;

use Illuminate\Console\Command;
use Lahatre\Shared\Registries\ResponseContractRegistry;

final class ResponseContractCacheCommand extends Command
{
    protected $signature = 'response-contracts:cache';

    protected $description = 'Create the response contract cache file';

    public function handle(ResponseContractRegistry $registry): int
    {
        $registry->cache();

        $this->components->info(__('shared::console.response_contracts.cached'));

        return self::SUCCESS;
    }
}

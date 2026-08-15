<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands;

use Illuminate\Console\Command;
use Lahatre\Shared\Registries\ResponseContractRegistry;

final class ResponseContractClearCommand extends Command
{
    protected $signature = 'response-contracts:clear';

    protected $description = 'Remove the response contract cache file';

    public function handle(ResponseContractRegistry $registry): int
    {
        $registry->clear();

        $this->components->info(__('shared::console.response_contracts.cleared'));

        return self::SUCCESS;
    }
}

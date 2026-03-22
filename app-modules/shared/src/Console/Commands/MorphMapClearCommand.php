<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands;

use Illuminate\Console\Command;
use Lahatre\Shared\Registries\MorphMapRegistry;

class MorphMapClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'morph-map:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the morph map cache file';

    /**
     * Execute the console command.
     */
    public function handle(MorphMapRegistry $registry): int
    {
        $registry->clear();

        $this->components->info('Morph map cache cleared successfully.');

        return self::SUCCESS;
    }
}

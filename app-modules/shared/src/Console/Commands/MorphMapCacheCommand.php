<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands;

use Illuminate\Console\Command;
use Lahatre\Shared\Registries\MorphMapRegistry;

class MorphMapCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'morph-map:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a cache file for faster morph map loading';

    /**
     * Execute the console command.
     */
    public function handle(MorphMapRegistry $registry): int
    {
        $this->call('morph-map:clear');

        $registry->cache();

        $this->components->info('Morph map cached successfully.');

        return self::SUCCESS;
    }
}

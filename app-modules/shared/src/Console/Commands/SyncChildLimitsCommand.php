<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands;

use Illuminate\Console\Command;
use Lahatre\Shared\Support\ChildLimitTriggers;

final class SyncChildLimitsCommand extends Command
{
    protected $signature = 'child-limits:sync {--check}';

    protected $description;

    public function __construct()
    {
        parent::__construct();
        $this->description = __('shared::console.child_limits_sync.description');
    }

    public function handle(ChildLimitTriggers $triggers): int
    {
        if (!$this->option('check')) {
            $triggers->sync();
            $this->components->info(__('shared::console.child_limits_sync.synced'));

            return self::SUCCESS;
        }

        ['missing' => $missing, 'obsolete' => $obsolete] = $triggers->differences();

        if ($missing === [] && $obsolete === []) {
            $this->components->info(__('shared::console.child_limits_sync.in_sync'));

            return self::SUCCESS;
        }

        foreach ($missing as $trigger) {
            $this->components->error(__('shared::console.child_limits_sync.missing', $trigger));
        }

        foreach ($obsolete as $trigger) {
            $this->components->error(__('shared::console.child_limits_sync.obsolete', $trigger));
        }

        return self::FAILURE;
    }
}

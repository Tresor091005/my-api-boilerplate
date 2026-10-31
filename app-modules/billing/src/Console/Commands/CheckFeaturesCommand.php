<?php

declare(strict_types=1);

namespace Lahatre\Billing\Console\Commands;

use Illuminate\Console\Command;
use Lahatre\Billing\Services\FeatureGrantCheck;

final class CheckFeaturesCommand extends Command
{
    protected $signature = 'billing:features:check';

    protected $description;

    public function __construct()
    {
        parent::__construct();
        $this->description = __('billing::console.features_check.description');
    }

    public function handle(FeatureGrantCheck $check): int
    {
        ['checked' => $checked, 'mismatches' => $mismatches] = $check->run();

        foreach ($mismatches as $mismatch) {
            $this->error($mismatch);
        }

        if ($mismatches !== []) {
            $this->error(__('billing::console.features_check.failed', ['count' => count($mismatches)]));

            return self::FAILURE;
        }

        $this->info(__('billing::console.features_check.passed', ['count' => $checked]));

        return self::SUCCESS;
    }
}

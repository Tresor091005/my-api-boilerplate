<?php

declare(strict_types=1);

namespace Lahatre\Billing\Console\Commands;

use Illuminate\Console\Command;
use Lahatre\Billing\Models\PlanVersionFeature;
use Lahatre\Billing\Registries\FeatureRegistry;

final class CheckFeaturesCommand extends Command
{
    protected $signature = 'billing:features:check';

    protected $description;

    public function __construct()
    {
        parent::__construct();
        $this->description = __('billing::console.features_check.description');
    }

    public function handle(FeatureRegistry $features): int
    {
        $definitions = $features->all();
        $grantsByFeature = PlanVersionFeature::query()
            ->select(['feature_key', 'feature_type'])
            ->orderBy('feature_key')
            ->get()
            ->groupBy('feature_key');
        $mismatches = 0;

        foreach ($grantsByFeature as $featureKey => $grants) {
            $definition = $definitions[$featureKey] ?? null;
            $grantedTypes = $grants
                ->map(fn (PlanVersionFeature $grant): string => $grant->feature_type->value)
                ->unique()
                ->sort()
                ->values();

            $mismatch = match (true) {
                $definition === null => __('billing::console.features_check.unknown_feature', [
                    'feature_key' => $featureKey,
                    'count'       => $grants->count(),
                ]),
                $grantedTypes->count() > 1 => __('billing::console.features_check.mixed_types', [
                    'feature_key'   => $featureKey,
                    'granted_types' => $grantedTypes->implode(', '),
                ]),
                !$definition->isDisabled() && $definition->type?->value !== $grantedTypes->first() => __('billing::console.features_check.type_changed', [
                    'feature_key'     => $featureKey,
                    'granted_type'    => $grantedTypes->first(),
                    'registered_type' => $definition->type?->value,
                ]),
                default => null,
            };

            if ($mismatch !== null) {
                $this->error($mismatch);
                $mismatches++;
            }
        }

        if ($mismatches > 0) {
            $this->error(__('billing::console.features_check.failed', ['count' => $mismatches]));

            return self::FAILURE;
        }

        $this->info(__('billing::console.features_check.passed', ['count' => $grantsByFeature->count()]));

        return self::SUCCESS;
    }
}

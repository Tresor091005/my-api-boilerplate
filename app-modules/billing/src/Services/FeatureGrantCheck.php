<?php

declare(strict_types=1);

namespace Lahatre\Billing\Services;

use Illuminate\Support\Collection;
use Lahatre\Billing\Models\PlanVersionFeature;
use Lahatre\Billing\Registries\FeatureRegistry;

/** Compares what plans grant with the features the code declares. */
final readonly class FeatureGrantCheck
{
    public function __construct(private FeatureRegistry $features) {}

    /**
     * One translated line per granted feature that no longer matches its declaration.
     *
     * @return array{checked: int, mismatches: list<string>}
     */
    public function run(): array
    {
        $definitions = $this->features->all();
        $grantsByFeature = PlanVersionFeature::query()
            ->select(['feature_key', 'feature_type', 'values'])
            ->orderBy('feature_key')
            ->get()
            ->groupBy('feature_key');
        $mismatches = [];

        foreach ($grantsByFeature as $featureKey => $grants) {
            /** @var Collection<int, PlanVersionFeature> $grants */
            $definition = $definitions[$featureKey] ?? null;
            $grantedTypes = $grants
                ->map(fn (PlanVersionFeature $grant): string => $grant->feature_type->value)
                ->unique()
                ->sort()
                ->values();
            $removedValues = $definition === null || $definition->isDisabled()
                ? collect()
                : $grants->pluck('values')->flatten()->filter()->unique()->diff($definition->options ?? [])->values();

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
                $removedValues->isNotEmpty() => __('billing::console.features_check.values_removed', [
                    'feature_key' => $featureKey,
                    'values'      => $removedValues->implode(', '),
                ]),
                default => null,
            };

            if ($mismatch !== null) {
                $mismatches[] = $mismatch;
            }
        }

        return ['checked' => $grantsByFeature->count(), 'mismatches' => $mismatches];
    }
}

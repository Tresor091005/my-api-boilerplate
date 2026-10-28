<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Billing\Http\Resources\Fields\PlanPrice;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\PlanVersionFeature;
use Lahatre\Billing\Registries\FeatureRegistry;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class OfferResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('code')
            ->field('name')
            ->field('price', columns: [], relations: ['currentVersion'], computedBy: fn (Model $plan): string => PlanPrice::decimal(self::current($plan)))
            ->field('currency_code', columns: [], relations: ['currentVersion'], computedBy: fn (Model $plan): string => self::current($plan)->currency_code)
            ->field('billing_interval', columns: [], relations: ['currentVersion'], computedBy: fn (Model $plan): int => self::current($plan)->billing_interval)
            ->field('features', columns: [], relations: ['currentVersion.features'], computedBy: fn (Model $plan): array => self::features($plan));
    }

    private static function current(Model $plan): PlanVersion
    {
        $current = $plan->getRelation('currentVersion');
        assert($current instanceof PlanVersion);

        return $current;
    }

    /** @return list<array{key: string, name: string, type: string, allowance: int|null}> */
    private static function features(Model $plan): array
    {
        $registry = app(FeatureRegistry::class);

        return self::current($plan)->features
            ->reject(fn (PlanVersionFeature $grant): bool => $registry->get($grant->feature_key)->isDisabled())
            ->sortBy('feature_key')
            ->map(fn (PlanVersionFeature $grant): array => [
                'key'       => $grant->feature_key,
                'name'      => __($registry->get($grant->feature_key)->translationKey()),
                'type'      => $grant->feature_type->value,
                'allowance' => $grant->allowance,
            ])
            ->values()
            ->all();
    }
}

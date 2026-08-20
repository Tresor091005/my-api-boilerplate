<?php

declare(strict_types=1);

namespace Lahatre\Billing;

use Carbon\CarbonImmutable;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Exceptions\BillingException;
use Lahatre\Billing\Models\Feature;
use Lahatre\Billing\Models\PlanVersionFeature;

final readonly class EntitlementService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private CapacityResolverRegistry $resolvers,
    ) {}

    public function canUse(string $organizationId, string $featureKey, ?CarbonImmutable $now = null): bool
    {
        return $this->resolve($organizationId, $featureKey, $now)['allowed'];
    }

    public function authorize(string $organizationId, string $featureKey, ?CarbonImmutable $now = null): void
    {
        $entitlement = $this->resolve($organizationId, $featureKey, $now);

        if (!$entitlement['allowed']) {
            throw new BillingException("Feature [{$featureKey}] is not available for this organization.");
        }
    }

    /**
     * @return array{
     *     allowed: bool,
     *     feature_key: string,
     *     feature_type: string|null,
     *     allowance: int|null,
     *     current_quantity: int|null,
     *     subscription_id: string|null,
     *     plan_version_id: string|null,
     * }
     */
    public function resolve(string $organizationId, string $featureKey, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $feature = Feature::query()->where('key', $featureKey)->first();
        $subscription = $this->subscriptions->current($organizationId);

        $base = [
            'allowed'          => false,
            'feature_key'      => $featureKey,
            'feature_type'     => $feature?->type?->value,
            'allowance'        => null,
            'current_quantity' => null,
            'subscription_id'  => $subscription?->getKey(),
            'plan_version_id'  => $subscription?->plan_version_id,
        ];

        if ($feature === null || !$feature->is_active || $subscription === null
            || !$this->subscriptions->hasProductAccess($subscription, $now)) {
            return $base;
        }

        $grant = $subscription->planVersion->features
            ->first(fn (PlanVersionFeature $candidate): bool => $candidate->feature_id === $feature->getKey());

        if ($grant === null) {
            return $base;
        }

        if ($feature->type === FeatureType::Boolean) {
            return [...$base, 'allowed' => true, 'feature_type' => FeatureType::Boolean->value];
        }

        if ($feature->resolver_key === null) {
            throw new BillingException("Capacity feature [{$featureKey}] has no resolver key.");
        }

        $quantity = $grant->allowance === null
            ? null
            : $this->resolvers->resolve($feature->resolver_key, $organizationId);

        return [
            ...$base,
            'allowed'          => $grant->allowance === null || $quantity < $grant->allowance,
            'feature_type'     => FeatureType::Capacity->value,
            'allowance'        => $grant->allowance,
            'current_quantity' => $quantity,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Billing\Services;

use Carbon\CarbonImmutable;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Exceptions\FeatureException;
use Lahatre\Billing\Models\PlanVersionFeature;
use Lahatre\Billing\Registries\FeatureRegistry;

final readonly class EntitlementService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private FeatureRegistry $features,
    ) {}

    /**
     * @throws FeatureException when the feature key is not registered
     */
    public function canUse(string $organizationId, string $featureKey, ?CarbonImmutable $now = null): bool
    {
        return $this->resolve($organizationId, $featureKey, $now)['allowed'];
    }

    /**
     * @throws FeatureException when the feature is not registered or not available for the organization
     */
    public function authorize(string $organizationId, string $featureKey, ?CarbonImmutable $now = null): void
    {
        $entitlement = $this->resolve($organizationId, $featureKey, $now);

        if (!$entitlement['allowed']) {
            throw FeatureException::unavailable($organizationId, $featureKey);
        }
    }

    /**
     * @throws FeatureException when the feature key is not registered
     *
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
        $definition = $this->features->get($featureKey);
        $subscription = $this->subscriptions->current($organizationId, $now)?->load('planVersion.features');

        $base = [
            'allowed'          => false,
            'feature_key'      => $featureKey,
            'feature_type'     => $definition->type?->value,
            'allowance'        => null,
            'current_quantity' => null,
            'subscription_id'  => $subscription?->getKey(),
            'plan_version_id'  => $subscription?->plan_version_id,
        ];

        if ($definition->isDisabled() || $subscription === null || !$subscription->statusAt($now)->grantsAccess()) {
            return $base;
        }

        $grant = $subscription->planVersion->features
            ->first(fn (PlanVersionFeature $candidate): bool => $candidate->feature_key === $featureKey);

        if ($grant === null) {
            return $base;
        }

        if ($definition->type === FeatureType::Boolean) {
            return [...$base, 'allowed' => true];
        }

        $quantity = $grant->allowance === null ? null : $this->features->count($featureKey, $organizationId);

        return [
            ...$base,
            'allowed'          => $grant->allowance === null || $quantity < $grant->allowance,
            'allowance'        => $grant->allowance,
            'current_quantity' => $quantity,
        ];
    }
}

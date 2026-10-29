<?php

declare(strict_types=1);

namespace Lahatre\Billing\Services;

use Carbon\CarbonImmutable;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Enums\FeatureUnavailableReason;
use Lahatre\Billing\Exceptions\FeatureException;
use Lahatre\Billing\Exceptions\FeatureUnavailableException;
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
     * @throws FeatureException when the feature key is not registered
     * @throws FeatureUnavailableException when the organization cannot use the feature, with the reason
     */
    public function authorize(string $organizationId, string $featureKey, ?CarbonImmutable $now = null): void
    {
        $entitlement = $this->resolve($organizationId, $featureKey, $now);

        if ($entitlement['allowed']) {
            return;
        }

        throw match (FeatureUnavailableReason::from((string) $entitlement['reason'])) {
            FeatureUnavailableReason::Disabled       => FeatureUnavailableException::disabled($organizationId, $featureKey),
            FeatureUnavailableReason::NoSubscription => FeatureUnavailableException::noSubscription($organizationId, $featureKey),
            FeatureUnavailableReason::Suspended      => FeatureUnavailableException::suspended($organizationId, $featureKey),
            FeatureUnavailableReason::NotInPlan      => FeatureUnavailableException::notInPlan($organizationId, $featureKey),
            FeatureUnavailableReason::LimitReached   => FeatureUnavailableException::limitReached(
                $organizationId,
                $featureKey,
                (int) $entitlement['allowance'],
                (int) $entitlement['current_quantity'],
            ),
        };
    }

    /**
     * The reason is null when the feature is allowed.
     *
     * @throws FeatureException when the feature key is not registered
     *
     * @return array{
     *     allowed: bool,
     *     reason: string|null,
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
            'reason'           => null,
            'feature_key'      => $featureKey,
            'feature_type'     => $definition->type?->value,
            'allowance'        => null,
            'current_quantity' => null,
            'subscription_id'  => $subscription?->getKey(),
            'plan_version_id'  => $subscription?->plan_version_id,
        ];

        if ($definition->isDisabled()) {
            return [...$base, 'reason' => FeatureUnavailableReason::Disabled->value];
        }

        if ($subscription === null) {
            return [...$base, 'reason' => FeatureUnavailableReason::NoSubscription->value];
        }

        if (!$subscription->statusAt($now)->grantsAccess()) {
            return [...$base, 'reason' => FeatureUnavailableReason::Suspended->value];
        }

        $grant = $subscription->planVersion->features
            ->first(fn (PlanVersionFeature $candidate): bool => $candidate->feature_key === $featureKey);

        if ($grant === null) {
            return [...$base, 'reason' => FeatureUnavailableReason::NotInPlan->value];
        }

        if ($definition->type === FeatureType::Boolean) {
            return [...$base, 'allowed' => true];
        }

        $quantity = $grant->allowance === null ? null : $this->features->count($featureKey, $organizationId);
        $allowed = $grant->allowance === null || $quantity < $grant->allowance;

        return [
            ...$base,
            'allowed'          => $allowed,
            'reason'           => $allowed ? null : FeatureUnavailableReason::LimitReached->value,
            'allowance'        => $grant->allowance,
            'current_quantity' => $quantity,
        ];
    }
}

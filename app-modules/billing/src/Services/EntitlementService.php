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
use Lahatre\Shared\Support\TransactionLock;
use LogicException;

final readonly class EntitlementService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private TrialService $trials,
        private FeatureRegistry $features,
    ) {}

    /**
     * @throws FeatureException when the feature key is not registered, or a value is missing or not expected
     */
    public function canUse(string $organizationId, string $featureKey, ?string $value = null, ?CarbonImmutable $now = null): bool
    {
        if (!config('billing.entitlements_enforced')) {
            return true;
        }

        return $this->resolve($organizationId, $featureKey, $value, $now)['allowed'];
    }

    /**
     * Call it inside the transaction that uses the feature: for a capacity, it
     * locks the feature for the organization until that transaction ends, so two
     * requests cannot both take the last unit.
     *
     * @throws FeatureException when the feature key is not registered, or a value is missing or not expected
     * @throws FeatureUnavailableException when the organization cannot use the feature, with the reason
     * @throws LogicException when a capacity is checked outside a transaction
     */
    public function authorize(string $organizationId, string $featureKey, ?string $value = null, ?CarbonImmutable $now = null): void
    {
        if (!config('billing.entitlements_enforced')) {
            return;
        }

        if ($this->features->get($featureKey)->type === FeatureType::Capacity) {
            TransactionLock::acquire("billing-entitlement:{$organizationId}:{$featureKey}");
        }

        $entitlement = $this->resolve($organizationId, $featureKey, $value, $now);

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
            FeatureUnavailableReason::ValueNotInPlan => FeatureUnavailableException::valueNotInPlan(
                $organizationId,
                $featureKey,
                (string) $value,
                $entitlement['values'] ?? [],
            ),
        };
    }

    /**
     * What the organization may do with the feature now; the reason is null when it is allowed.
     * A choice feature is resolved for one value; `values` lists those the plan grants.
     *
     * @throws FeatureException when the feature key is not registered, or a value is missing or not expected
     *
     * @return array{
     *     allowed: bool,
     *     reason: string|null,
     *     feature_key: string,
     *     feature_type: string|null,
     *     allowance: int|null,
     *     current_quantity: int|null,
     *     values: list<string>|null,
     *     subscription_id: string|null,
     *     plan_version_id: string|null,
     * }
     */
    public function resolve(string $organizationId, string $featureKey, ?string $value = null, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $definition = $this->features->get($featureKey);

        if (($definition->type === FeatureType::Choice) !== ($value !== null) && !$definition->isDisabled()) {
            throw FeatureException::valueRequired($featureKey);
        }

        $subscription = $this->subscriptions->current($organizationId, $now)?->load('planVersion.features');
        $version = $subscription?->statusAt($now)->grantsAccess() === true
            ? $subscription->planVersion
            : $this->trials->runningAt($organizationId, $now)?->load('planVersion.features')->planVersion;

        $base = [
            'allowed'          => false,
            'reason'           => null,
            'feature_key'      => $featureKey,
            'feature_type'     => $definition->type?->value,
            'allowance'        => null,
            'current_quantity' => null,
            'values'           => null,
            'subscription_id'  => $subscription?->getKey(),
            'plan_version_id'  => $version?->getKey() ?? $subscription?->plan_version_id,
        ];

        if ($definition->isDisabled()) {
            return [...$base, 'reason' => FeatureUnavailableReason::Disabled->value];
        }

        if ($version === null) {
            return [...$base, 'reason' => $subscription === null
                ? FeatureUnavailableReason::NoSubscription->value
                : FeatureUnavailableReason::Suspended->value];
        }

        $grant = $version->features
            ->first(fn (PlanVersionFeature $candidate): bool => $candidate->feature_key === $featureKey);

        if ($grant === null) {
            return [...$base, 'reason' => FeatureUnavailableReason::NotInPlan->value];
        }

        if ($definition->type === FeatureType::Boolean) {
            return [...$base, 'allowed' => true];
        }

        if ($definition->type === FeatureType::Choice) {
            $allowed = in_array($value, $grant->values ?? [], true);

            return [
                ...$base,
                'allowed' => $allowed,
                'reason'  => $allowed ? null : FeatureUnavailableReason::ValueNotInPlan->value,
                'values'  => $grant->values ?? [],
            ];
        }

        $since = $definition->window === null ? null : $now->sub($definition->window);
        $quantity = $grant->allowance === null ? null : $this->features->count($featureKey, $organizationId, $since);
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

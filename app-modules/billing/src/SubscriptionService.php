<?php

declare(strict_types=1);

namespace Lahatre\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\Enums\CollectionMethod;
use Lahatre\Billing\Enums\PlanVersionStatus;
use Lahatre\Billing\Enums\SubscriptionStatus;
use Lahatre\Billing\Exceptions\BillingException;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Billing\Support\BillingAnchorCalculator;

final readonly class SubscriptionService
{
    public function __construct(private BillingAnchorCalculator $anchorCalculator) {}

    public function current(string $organizationId): ?Subscription
    {
        return Subscription::query()
            ->where('organization_id', $organizationId)
            ->where('is_current', true)
            ->with(['planVersion.plan', 'planVersion.features.feature'])
            ->first();
    }

    public function currentSubscription(string $organizationId): ?Subscription
    {
        return $this->current($organizationId);
    }

    public function currentPlan(string $organizationId): ?Plan
    {
        return $this->current($organizationId)?->planVersion?->plan;
    }

    public function create(
        string $organizationId,
        PlanVersion $planVersion,
        CarbonImmutable $periodStart,
        int $billingAnchorDay,
        CollectionMethod $collectionMethod,
    ): Subscription {
        return DB::transaction(function () use (
            $organizationId, $planVersion, $periodStart,
            $billingAnchorDay, $collectionMethod,
        ): Subscription {
            if ($planVersion->status !== PlanVersionStatus::Published) {
                throw new BillingException('A subscription must start from a published plan version.');
            }

            if ($this->current($organizationId) !== null) {
                throw new BillingException('The organization already has a current subscription.');
            }

            $periodEnd = $this->periodEnd($planVersion, $periodStart, $billingAnchorDay);

            return Subscription::query()->create([
                'organization_id'      => $organizationId,
                'plan_version_id'      => $planVersion->getKey(),
                'is_current'           => true,
                'status'               => SubscriptionStatus::Active,
                'current_period_start' => $periodStart,
                'current_period_end'   => $periodEnd,
                'billing_anchor_day'   => $billingAnchorDay,
                'collection_method'    => $collectionMethod,
                'cancel_at_period_end' => false,
                'grace_ends_at'        => null,
            ]);
        });
    }

    public function requestCancellation(Subscription $subscription, CarbonImmutable $now): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::Active || $subscription->current_period_end <= $now) {
            throw new BillingException('Only an active subscription in its paid period can be cancelled at period end.');
        }

        $subscription->update(['cancel_at_period_end' => true, 'cancelled_at' => $now]);

        return $subscription->refresh();
    }

    public function resume(Subscription $subscription): Subscription
    {
        if (!$subscription->cancel_at_period_end) {
            return $subscription;
        }

        if ($subscription->status !== SubscriptionStatus::Active
            || $subscription->current_period_end <= CarbonImmutable::now()) {
            throw new BillingException('Only an active subscription before period end can be resumed.');
        }

        $subscription->update(['cancel_at_period_end' => false, 'cancelled_at' => null]);

        return $subscription->refresh();
    }

    public function markPeriodEnded(Subscription $subscription, CarbonImmutable $now, int $graceDays = 5): Subscription
    {
        if ($subscription->current_period_end > $now || $subscription->status !== SubscriptionStatus::Active) {
            throw new BillingException('Only an active subscription at or after period end can enter grace.');
        }

        if ($subscription->cancel_at_period_end) {
            $subscription->update([
                'status'        => SubscriptionStatus::Cancelled,
                'is_current'    => false,
                'grace_ends_at' => null,
            ]);
        } else {
            $subscription->update([
                'status'        => SubscriptionStatus::PastDue,
                'grace_ends_at' => $now->addDays($graceDays),
            ]);
        }

        return $subscription->refresh();
    }

    public function renew(Subscription $subscription, CarbonImmutable $confirmedAt): Subscription
    {
        if ($subscription->cancel_at_period_end) {
            throw new BillingException('A subscription scheduled for cancellation cannot be renewed.');
        }

        if (!in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
            throw new BillingException('Only active or past-due subscriptions can be renewed.');
        }

        if ($subscription->status === SubscriptionStatus::PastDue
            && ($subscription->grace_ends_at === null || $confirmedAt >= $subscription->grace_ends_at)) {
            throw new BillingException('The subscription grace period has ended.');
        }

        $planVersion = $subscription->planVersion()->firstOrFail();
        $periodStart = $subscription->current_period_end;
        $periodEnd = $this->periodEnd($planVersion, $periodStart, $subscription->billing_anchor_day);

        $subscription->update([
            'status'               => SubscriptionStatus::Active,
            'current_period_start' => $periodStart,
            'current_period_end'   => $periodEnd,
            'grace_ends_at'        => null,
        ]);

        return $subscription->refresh();
    }

    public function suspendExpiredGrace(Subscription $subscription, CarbonImmutable $now): Subscription
    {
        if ($subscription->status !== SubscriptionStatus::PastDue
            || $subscription->grace_ends_at === null
            || $now < $subscription->grace_ends_at) {
            throw new BillingException('The subscription is not ready to be suspended.');
        }

        $subscription->update(['status' => SubscriptionStatus::Suspended, 'grace_ends_at' => null]);

        return $subscription->refresh();
    }

    public function hasProductAccess(Subscription $subscription, CarbonImmutable $now): bool
    {
        return match ($subscription->status) {
            SubscriptionStatus::Active  => $subscription->current_period_end > $now,
            SubscriptionStatus::PastDue => $subscription->grace_ends_at !== null
                && $now < $subscription->grace_ends_at,
            default => false,
        };
    }

    private function periodEnd(PlanVersion $planVersion, CarbonImmutable $periodStart, int $billingAnchorDay): CarbonImmutable
    {
        return $this->anchorCalculator->periodEnd($periodStart, $billingAnchorDay, $planVersion->billing_interval);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Billing\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\Data\CheckoutData;
use Lahatre\Billing\Data\CheckoutFilterData;
use Lahatre\Billing\Enums\CheckoutAction;
use Lahatre\Billing\Enums\CheckoutStatus;
use Lahatre\Billing\Enums\PlanVersionStatus;
use Lahatre\Billing\Enums\SubscriptionStatus;
use Lahatre\Billing\Exceptions\CheckoutException;
use Lahatre\Billing\Exceptions\SubscriptionException;
use Lahatre\Billing\Models\Checkout;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Billing\Support\BillingCalendar;

final readonly class CheckoutService
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private TrialService $trials,
        private BillingCalendar $calendar,
    ) {}

    /**
     * @throws SubscriptionException when the action is not allowed for the organization's subscription at this moment
     */
    public function start(string $organizationId, CheckoutData $data): Checkout
    {
        $periods = $data->action === CheckoutAction::ChangeNow ? 1 : $data->periods;
        $this->assertPeriods($periods);

        return DB::transaction(function () use ($organizationId, $data, $periods): Checkout {
            $this->lockOrganization($organizationId);
            $now = CarbonImmutable::now()->startOfSecond();
            $current = $this->subscriptions->current($organizationId, $now)?->load('planVersion');
            $next = $this->subscriptions->next($organizationId, $now)?->load('planVersion');
            $version = $data->planId === null
                ? $this->lastPaidVersion($organizationId, $current, $next)
                : $this->versionOnSale($data->planId);

            $quote = match ($data->action) {
                CheckoutAction::Subscribe => $this->quoteSubscription($organizationId, $version, $periods, $current, $now),
                CheckoutAction::Renew     => $this->quoteRenewal($organizationId, $version, $periods, $current, $next, $now),
                CheckoutAction::ChangeNow => $this->quoteChange($organizationId, $version, $current, $next, $now),
            };

            $this->assertWithinAdvanceLimit($quote['covers_until'], $now);
            $this->cancelPendingCheckouts($organizationId);

            return Checkout::query()->create([
                'organization_id' => $organizationId,
                'plan_version_id' => $version->getKey(),
                'action'          => $data->action,
                'periods'         => $periods,
                'currency_code'   => $version->currency_code,
                'status'          => CheckoutStatus::Pending,
                'started_at'      => $now,
                'expires_at'      => $now->addMinutes(Checkout::EXPIRES_AFTER_MINUTES),
                ...$quote,
            ]);
        });
    }

    /**
     * @throws CheckoutException when the checkout was replaced, has expired, or no longer matches the subscription
     */
    public function confirm(Checkout $checkout): Subscription
    {
        // TODO: check the amount and currency the provider says were paid against the checkout before applying it.
        // TODO: keep the provider's payment id, so a webhook sent twice is recognised; its signature is checked at the route.
        return DB::transaction(function () use ($checkout): Subscription {
            $this->lockOrganization($checkout->organization_id);
            $checkout = Checkout::query()->lockForUpdate()->findOrFail($checkout->getKey());

            if ($checkout->status === CheckoutStatus::Confirmed) {
                return Subscription::withTrashed()->findOrFail($checkout->subscription_id);
            }

            if ($checkout->status === CheckoutStatus::Cancelled) {
                throw CheckoutException::cancelled($checkout->getKey());
            }

            $now = CarbonImmutable::now();

            if ($now >= $checkout->expires_at) {
                throw CheckoutException::expired($checkout->getKey());
            }

            $this->lockPlanVersion($checkout->plan_version_id);

            $subscription = match ($checkout->action) {
                CheckoutAction::Subscribe => $this->applySubscription($checkout),
                CheckoutAction::Renew     => $this->applyRenewal($checkout),
                CheckoutAction::ChangeNow => $this->applyChange($checkout),
            };
            $checkout->update([
                'status'          => CheckoutStatus::Confirmed,
                'confirmed_at'    => $now,
                'subscription_id' => $subscription->getKey(),
            ]);

            return $subscription;
        });
    }

    /** @return CursorPaginator<int, Checkout> */
    public function getCheckouts(string $organizationId, CheckoutFilterData $filters): CursorPaginator
    {
        $query = Checkout::query()->where('organization_id', $organizationId);

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        return stableCursorPaginate($query, $filters);
    }

    /** @return array{covers_from: CarbonImmutable, covers_until: CarbonImmutable, total_amount: int} */
    private function quoteSubscription(string $organizationId, PlanVersion $version, int $periods, ?Subscription $current, CarbonImmutable $now): array
    {
        if ($current?->statusAt($now)->grantsAccess() === true) {
            throw SubscriptionException::alreadySubscribed($organizationId);
        }

        $this->assertOnSale($version);

        return [
            'covers_from'  => $now,
            'covers_until' => $this->calendar->addPeriods($now, $version->billing_interval_months, $periods),
            'total_amount' => $version->price * $periods,
        ];
    }

    /** @return array{covers_from: CarbonImmutable, covers_until: CarbonImmutable, total_amount: int} */
    private function quoteRenewal(string $organizationId, PlanVersion $version, int $periods, ?Subscription $current, ?Subscription $next, CarbonImmutable $now): array
    {
        if ($current === null || !$current->statusAt($now)->grantsAccess()) {
            throw SubscriptionException::notSubscribed($organizationId);
        }

        $lastPaid = $next ?? $current;

        if ($lastPaid->plan_version_id === $version->getKey()) {
            return [
                'covers_from'  => $lastPaid->ends_at,
                'covers_until' => $this->endAfterRenewal($lastPaid, $periods),
                'total_amount' => $version->price * $periods,
            ];
        }

        if ($next !== null) {
            throw SubscriptionException::nextPlanAlreadyChosen($next->getKey());
        }

        if ($this->isPaidInAdvance($current, $now)) {
            throw SubscriptionException::currentPlanPaidInAdvance($current->getKey());
        }

        $this->assertOnSale($version);

        return [
            'covers_from'  => $current->ends_at,
            'covers_until' => $this->calendar->addPeriods($current->ends_at, $version->billing_interval_months, $periods),
            'total_amount' => $version->price * $periods,
        ];
    }

    /** @return array{covers_from: CarbonImmutable, covers_until: CarbonImmutable, total_amount: int} */
    private function quoteChange(string $organizationId, PlanVersion $version, ?Subscription $current, ?Subscription $next, CarbonImmutable $now): array
    {
        if ($current === null || $current->statusAt($now) !== SubscriptionStatus::Active) {
            throw SubscriptionException::changeRequiresActiveSubscription($organizationId);
        }

        if ($current->plan_version_id === $version->getKey()) {
            throw SubscriptionException::changeToSamePlan($version->getKey());
        }

        $this->assertOnSale($version);
        $paidSubscriptions = array_filter([$current, $next]);

        foreach ($paidSubscriptions as $paid) {
            if ($paid->planVersion->currency_code !== $version->currency_code) {
                throw SubscriptionException::currencyMismatch($paid->planVersion->currency_code, $version->currency_code);
            }
        }

        $remainingValue = array_sum(array_map(fn (Subscription $paid): int => $this->unusedValue($paid, $now), $paidSubscriptions));

        if ($remainingValue >= $version->price) {
            throw SubscriptionException::remainingValueCoversPrice($remainingValue, $version->price);
        }

        return [
            'covers_from'  => $now,
            'covers_until' => $this->calendar->addPeriods($now, $version->billing_interval_months, 1),
            'total_amount' => $version->price - $remainingValue,
        ];
    }

    private function endAfterRenewal(Subscription $subscription, int $periods): CarbonImmutable
    {
        $months = $subscription->planVersion->billing_interval_months;
        $paidPeriods = $this->calendar->periodAt($subscription->starts_at, $months, $subscription->ends_at);

        return $this->calendar->addPeriods($subscription->starts_at, $months, $paidPeriods + $periods);
    }

    private function periodEndAt(Subscription $subscription, CarbonImmutable $at): CarbonImmutable
    {
        $months = $subscription->planVersion->billing_interval_months;
        $passedPeriods = $this->calendar->periodAt($subscription->starts_at, $months, $at);

        return $this->calendar->addPeriods($subscription->starts_at, $months, $passedPeriods + 1);
    }

    private function isPaidInAdvance(Subscription $subscription, CarbonImmutable $now): bool
    {
        return $now < $subscription->ends_at && $this->periodEndAt($subscription, $now) < $subscription->ends_at;
    }

    private function unusedValue(Subscription $subscription, CarbonImmutable $now): int
    {
        $from = $now->max($subscription->starts_at);

        if ($from >= $subscription->ends_at) {
            return 0;
        }

        $price = $subscription->planVersion->price;
        $months = $subscription->planVersion->billing_interval_months;
        $passedPeriods = $this->calendar->periodAt($subscription->starts_at, $months, $from);
        $periodStart = $this->calendar->addPeriods($subscription->starts_at, $months, $passedPeriods);
        $periodEnd = $this->calendar->addPeriods($subscription->starts_at, $months, $passedPeriods + 1);
        $paidPeriods = $this->calendar->periodAt($subscription->starts_at, $months, $subscription->ends_at);
        $fullPeriodsLeft = $paidPeriods - $passedPeriods - 1;

        $secondsLeft = $periodEnd->getTimestamp() - $from->getTimestamp();
        $periodSeconds = $periodEnd->getTimestamp() - $periodStart->getTimestamp();
        $currentPeriodValueLeft = (int) bcdiv(bcmul((string) $price, (string) $secondsLeft), (string) $periodSeconds, 0);

        return $currentPeriodValueLeft + $fullPeriodsLeft * $price;
    }

    private function applySubscription(Checkout $checkout): Subscription
    {
        $this->trials->endAt($checkout->organization_id, $checkout->covers_from);

        return Subscription::query()->create([
            'organization_id' => $checkout->organization_id,
            'plan_version_id' => $checkout->plan_version_id,
            'starts_at'       => $checkout->covers_from,
            'ends_at'         => $checkout->covers_until,
        ]);
    }

    private function applyRenewal(Checkout $checkout): Subscription
    {
        $lastPaid = Subscription::query()
            ->where('organization_id', $checkout->organization_id)
            ->latest('starts_at')
            ->first();

        if ($lastPaid === null || !$lastPaid->ends_at->equalTo($checkout->covers_from)) {
            throw CheckoutException::outdated($checkout->getKey());
        }

        if ($lastPaid->plan_version_id !== $checkout->plan_version_id) {
            return $this->applySubscription($checkout);
        }

        $lastPaid->update(['ends_at' => $checkout->covers_until]);

        return $lastPaid;
    }

    private function applyChange(Checkout $checkout): Subscription
    {
        $changeAt = $checkout->covers_from;
        $current = Subscription::query()
            ->where('organization_id', $checkout->organization_id)
            ->where('starts_at', '<=', $changeAt)
            ->where('ends_at', '>', $changeAt)
            ->first() ?? throw CheckoutException::outdated($checkout->getKey());

        Subscription::query()
            ->where('organization_id', $checkout->organization_id)
            ->where('starts_at', '>', $changeAt)
            ->delete();

        if ($current->starts_at->equalTo($changeAt)) {
            $current->delete();
        } else {
            $current->update(['ends_at' => $changeAt]);
        }

        return $this->applySubscription($checkout);
    }

    private function assertPeriods(int $periods): void
    {
        if ($periods < 1) {
            throw SubscriptionException::invalidPeriods($periods);
        }

        if ($periods > $this->maxAdvanceMonths()) {
            throw SubscriptionException::advanceLimitExceeded($this->maxAdvanceMonths());
        }
    }

    private function assertWithinAdvanceLimit(CarbonImmutable $coversUntil, CarbonImmutable $now): void
    {
        if ($coversUntil > $now->addMonthsNoOverflow($this->maxAdvanceMonths())) {
            throw SubscriptionException::advanceLimitExceeded($this->maxAdvanceMonths());
        }
    }

    private function lastPaidVersion(string $organizationId, ?Subscription $current, ?Subscription $next): PlanVersion
    {
        $lastPaid = $next ?? $current ?? throw SubscriptionException::notSubscribed($organizationId);

        return $lastPaid->planVersion;
    }

    private function versionOnSale(string $planId): PlanVersion
    {
        return PlanVersion::query()
            ->where('plan_id', $planId)
            ->where('status', PlanVersionStatus::Published)
            ->whereRelation('plan', 'is_trial', false)
            ->sharedLock()
            ->first() ?? throw SubscriptionException::planNotOnSale($planId);
    }

    private function assertOnSale(PlanVersion $version): void
    {
        if ($version->status !== PlanVersionStatus::Published) {
            throw SubscriptionException::planNotOnSale($version->plan_id);
        }
    }

    private function maxAdvanceMonths(): int
    {
        return (int) config('billing.max_advance_months');
    }

    private function cancelPendingCheckouts(string $organizationId): void
    {
        Checkout::query()
            ->where('organization_id', $organizationId)
            ->where('status', CheckoutStatus::Pending)
            ->update(['status' => CheckoutStatus::Cancelled]);
    }

    private function lockOrganization(string $organizationId): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['billing-subscription:'.$organizationId]);
    }

    private function lockPlanVersion(string $planVersionId): void
    {
        PlanVersion::query()->sharedLock()->findOrFail($planVersionId);
    }
}

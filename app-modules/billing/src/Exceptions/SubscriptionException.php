<?php

declare(strict_types=1);

namespace Lahatre\Billing\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class SubscriptionException extends AssertionException
{
    public static function alreadySubscribed(string $organizationId): self
    {
        return new self(__('billing::exceptions.subscription.already_subscribed'), [
            'organization_id' => $organizationId,
        ]);
    }

    public static function notSubscribed(string $organizationId): self
    {
        return new self(__('billing::exceptions.subscription.not_subscribed'), [
            'organization_id' => $organizationId,
        ]);
    }

    public static function planNotOnSale(string $planId): self
    {
        return new self(__('billing::exceptions.subscription.plan_not_on_sale'), [
            'plan_id' => $planId,
        ]);
    }

    public static function invalidPeriods(int $periods): self
    {
        return new self(__('billing::exceptions.subscription.invalid_periods'), [
            'periods' => $periods,
        ]);
    }

    public static function advanceLimitExceeded(int $maxAdvanceMonths): self
    {
        return new self(__('billing::exceptions.subscription.advance_limit_exceeded', ['months' => $maxAdvanceMonths]), [
            'max_advance_months' => $maxAdvanceMonths,
        ]);
    }

    public static function nextPlanAlreadyChosen(string $subscriptionId): self
    {
        return new self(__('billing::exceptions.subscription.next_plan_already_chosen'), [
            'subscription_id' => $subscriptionId,
        ]);
    }

    public static function currentPlanPaidInAdvance(string $subscriptionId): self
    {
        return new self(__('billing::exceptions.subscription.current_plan_paid_in_advance'), [
            'subscription_id' => $subscriptionId,
        ]);
    }

    public static function changeRequiresActiveSubscription(string $organizationId): self
    {
        return new self(__('billing::exceptions.subscription.change_requires_active_subscription'), [
            'organization_id' => $organizationId,
        ]);
    }

    public static function changeToSamePlan(string $planVersionId): self
    {
        return new self(__('billing::exceptions.subscription.change_to_same_plan'), [
            'plan_version_id' => $planVersionId,
        ]);
    }

    public static function currencyMismatch(string $currencyCode, string $targetCurrencyCode): self
    {
        return new self(__('billing::exceptions.subscription.currency_mismatch'), [
            'currency_code'        => $currencyCode,
            'target_currency_code' => $targetCurrencyCode,
        ]);
    }

    public static function remainingValueCoversPrice(int $remainingValue, int $price): self
    {
        return new self(__('billing::exceptions.subscription.remaining_value_covers_price'), [
            'remaining_value' => $remainingValue,
            'price'           => $price,
        ]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Billing\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class PlanVersionException extends AssertionException
{
    public static function termsRequired(string $planId): self
    {
        return new self(__('billing::exceptions.plan_version.terms_required'), [
            'plan_id' => $planId,
        ]);
    }

    public static function priceWithoutCurrency(): self
    {
        return new self(__('billing::exceptions.plan_version.price_without_currency'));
    }

    public static function pricePrecisionExceeded(string $price, string $currencyCode, int $precision): self
    {
        return new self(__('billing::exceptions.plan_version.price_precision_exceeded'), [
            'price'         => $price,
            'currency_code' => $currencyCode,
            'precision'     => $precision,
        ]);
    }

    public static function billingIntervalInvalid(mixed $billingInterval): self
    {
        return new self(__('billing::exceptions.plan_version.billing_interval_invalid'), [
            'billing_interval' => $billingInterval,
        ]);
    }

    public static function draftMissing(string $planId): self
    {
        return new self(__('billing::exceptions.plan_version.draft_missing'), [
            'plan_id' => $planId,
        ]);
    }

    public static function notOnSale(string $planId): self
    {
        return new self(__('billing::exceptions.plan_version.not_on_sale'), [
            'plan_id' => $planId,
        ]);
    }

    public static function featureDisabled(string $featureKey): self
    {
        return new self(__('billing::exceptions.plan_version.feature_disabled'), [
            'feature_key' => $featureKey,
        ]);
    }

    public static function featureTypeChanged(string $featureKey): self
    {
        return new self(__('billing::exceptions.plan_version.feature_type_changed'), [
            'feature_key' => $featureKey,
        ]);
    }

    public static function negativeAllowance(string $featureKey, int $allowance): self
    {
        return new self(__('billing::exceptions.plan_version.negative_allowance'), [
            'feature_key' => $featureKey,
            'allowance'   => $allowance,
        ]);
    }

    public static function booleanFeatureAllowance(string $featureKey): self
    {
        return new self(__('billing::exceptions.plan_version.boolean_feature_allowance'), [
            'feature_key' => $featureKey,
        ]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}

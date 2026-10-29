<?php

declare(strict_types=1);

namespace Lahatre\Billing\Exceptions;

use Lahatre\Billing\Enums\FeatureUnavailableReason;
use Lahatre\Shared\Exceptions\AssertionException;

/**
 * An organization uses a feature its subscription does not give.
 * The API renders it as a 403 that tells the client why.
 */
final class FeatureUnavailableException extends AssertionException
{
    public static function disabled(string $organizationId, string $featureKey): self
    {
        return self::because(
            __('billing::exceptions.feature_unavailable.disabled'),
            FeatureUnavailableReason::Disabled,
            $organizationId,
            $featureKey,
        );
    }

    public static function noSubscription(string $organizationId, string $featureKey): self
    {
        return self::because(
            __('billing::exceptions.feature_unavailable.no_subscription'),
            FeatureUnavailableReason::NoSubscription,
            $organizationId,
            $featureKey,
        );
    }

    public static function suspended(string $organizationId, string $featureKey): self
    {
        return self::because(
            __('billing::exceptions.feature_unavailable.suspended'),
            FeatureUnavailableReason::Suspended,
            $organizationId,
            $featureKey,
        );
    }

    public static function notInPlan(string $organizationId, string $featureKey): self
    {
        return self::because(
            __('billing::exceptions.feature_unavailable.not_in_plan'),
            FeatureUnavailableReason::NotInPlan,
            $organizationId,
            $featureKey,
        );
    }

    public static function limitReached(string $organizationId, string $featureKey, int $allowance, int $currentQuantity): self
    {
        return self::because(
            __('billing::exceptions.feature_unavailable.limit_reached', ['allowance' => $allowance]),
            FeatureUnavailableReason::LimitReached,
            $organizationId,
            $featureKey,
            $allowance,
            $currentQuantity,
        );
    }

    private static function because(
        string $message,
        FeatureUnavailableReason $reason,
        string $organizationId,
        string $featureKey,
        ?int $allowance = null,
        ?int $currentQuantity = null,
    ): self {
        return new self($message, [
            'organization_id'  => $organizationId,
            'reason'           => $reason->value,
            'feature_key'      => $featureKey,
            'allowance'        => $allowance,
            'current_quantity' => $currentQuantity,
        ]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}

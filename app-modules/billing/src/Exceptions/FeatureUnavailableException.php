<?php

declare(strict_types=1);

namespace Lahatre\Billing\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Lahatre\Billing\Enums\FeatureUnavailableReason;
use Lahatre\Shared\Exceptions\AssertionException;

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

    /** @param  list<string>  $allowedValues */
    public static function valueNotInPlan(string $organizationId, string $featureKey, string $value, array $allowedValues): self
    {
        return self::because(
            __('billing::exceptions.feature_unavailable.value_not_in_plan'),
            FeatureUnavailableReason::ValueNotInPlan,
            $organizationId,
            $featureKey,
            extra: ['value' => $value, 'values' => $allowedValues],
        );
    }

    /** Unlike other assertions, the context is shown in production: the client needs the reason and the limit. */
    public function render(Request $request): ?JsonResponse
    {
        if (!$request->expectsJson()) {
            return null;
        }

        return response()->json([
            'message' => $this->getMessage(),
            'errors'  => [
                'type' => class_basename($this),
                ...Arr::except($this->context(), ['organization_id']),
            ],
        ], 403);
    }

    private static function because(
        string $message,
        FeatureUnavailableReason $reason,
        string $organizationId,
        string $featureKey,
        ?int $allowance = null,
        ?int $currentQuantity = null,
        array $extra = [],
    ): self {
        return new self($message, [
            'organization_id'  => $organizationId,
            'reason'           => $reason->value,
            'feature_key'      => $featureKey,
            'allowance'        => $allowance,
            'current_quantity' => $currentQuantity,
            ...$extra,
        ]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}

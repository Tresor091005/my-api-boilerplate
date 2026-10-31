<?php

declare(strict_types=1);

namespace Lahatre\Billing\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lahatre\Billing\Enums\FeatureUnavailableReason;
use Lahatre\Shared\Exceptions\AssertionException;

final class SubscriptionRequiredException extends AssertionException
{
    public static function noSubscription(string $organizationId): self
    {
        return new self(__('billing::exceptions.subscription_required.no_subscription'), [
            'organization_id' => $organizationId,
            'reason'          => FeatureUnavailableReason::NoSubscription->value,
        ]);
    }

    public static function suspended(string $organizationId): self
    {
        return new self(__('billing::exceptions.subscription_required.suspended'), [
            'organization_id' => $organizationId,
            'reason'          => FeatureUnavailableReason::Suspended->value,
        ]);
    }

    /** Unlike other assertions, the reason is shown in production: the client needs it to offer a plan. */
    public function render(Request $request): ?JsonResponse
    {
        if (!$request->expectsJson()) {
            return null;
        }

        return response()->json([
            'message' => $this->getMessage(),
            'errors'  => [
                'type'   => class_basename($this),
                'reason' => $this->context()['reason'],
            ],
        ], 403);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Lahatre\Billing\Exceptions\SubscriptionRequiredException;
use Lahatre\Billing\Services\SubscriptionService;
use Lahatre\Billing\Services\TrialService;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureSubscriptionAccess
{
    public function __construct(
        private SubscriptionService $subscriptions,
        private TrialService $trials,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws SubscriptionRequiredException when the organization has neither a subscription giving access nor a running trial
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('billing.subscription_required')) {
            return $next($request);
        }

        $organizationId = currentOrganizationId();
        $now = CarbonImmutable::now();
        $subscription = $this->subscriptions->current($organizationId, $now);

        if ($subscription?->statusAt($now)->grantsAccess() === true || $this->trials->runningAt($organizationId, $now) !== null) {
            return $next($request);
        }

        throw $subscription === null
            ? SubscriptionRequiredException::noSubscription($organizationId)
            : SubscriptionRequiredException::suspended($organizationId);
    }
}

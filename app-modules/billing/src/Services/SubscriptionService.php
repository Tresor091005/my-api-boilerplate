<?php

declare(strict_types=1);

namespace Lahatre\Billing\Services;

use Carbon\CarbonImmutable;
use Lahatre\Billing\Models\Subscription;

final readonly class SubscriptionService
{
    /** The subscription in effect at $at. */
    public function current(string $organizationId, CarbonImmutable $at): ?Subscription
    {
        return Subscription::query()
            ->where('organization_id', $organizationId)
            ->where('starts_at', '<=', $at)
            ->latest('starts_at')
            ->first();
    }

    /** The next plan already paid, starting after $at. */
    public function next(string $organizationId, CarbonImmutable $at): ?Subscription
    {
        return Subscription::query()
            ->where('organization_id', $organizationId)
            ->where('starts_at', '>', $at)
            ->oldest('starts_at')
            ->first();
    }
}

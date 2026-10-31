<?php

declare(strict_types=1);

namespace Lahatre\Billing\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Billing\Models\Trial;
use Lahatre\Billing\Support\BillingCalendar;

final readonly class TrialService
{
    public function __construct(private BillingCalendar $calendar) {}

    /** One period of the trial plan's published version, for an organization that never had a trial nor a subscription. */
    public function start(string $organizationId): ?Trial
    {
        return DB::transaction(function () use ($organizationId): ?Trial {
            if (
                Trial::query()->where('organization_id', $organizationId)->exists()
                || Subscription::withTrashed()->where('organization_id', $organizationId)->exists()
            ) {
                return null;
            }

            $version = Plan::query()->where('is_trial', true)->sharedLock()->first()?->currentVersion()->first();

            if ($version === null) {
                return null;
            }

            $now = CarbonImmutable::now()->startOfSecond();

            return Trial::query()->create([
                'organization_id' => $organizationId,
                'plan_version_id' => $version->getKey(),
                'starts_at'       => $now,
                'ends_at'         => $this->calendar->addPeriods($now, $version->billing_interval_months, 1),
            ]);
        });
    }

    public function find(string $organizationId): ?Trial
    {
        return Trial::query()->where('organization_id', $organizationId)->first();
    }

    public function runningAt(string $organizationId, CarbonImmutable $at): ?Trial
    {
        return Trial::query()
            ->where('organization_id', $organizationId)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>', $at)
            ->first();
    }

    /** A first subscription ends the trial when it starts. */
    public function endAt(string $organizationId, CarbonImmutable $at): void
    {
        Trial::query()
            ->where('organization_id', $organizationId)
            ->where('ends_at', '>', $at)
            ->update(['ends_at' => $at]);
    }
}

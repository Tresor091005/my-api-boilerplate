<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Billing\Http\Resources\Fields\PlanPrice;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\Subscription;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class SubscriptionResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('status', columns: ['ends_at'], computedBy: fn (Model $subscription): string => self::subscription($subscription)->statusAt(CarbonImmutable::now())->value)
            ->field('plan_id', columns: [], relations: ['planVersion'], computedBy: fn (Model $subscription): string => self::version($subscription)->plan_id)
            ->field('plan_name', columns: [], relations: ['planVersion.plan'], computedBy: fn (Model $subscription): string => self::version($subscription)->plan->name)
            ->field('price', columns: [], relations: ['planVersion'], computedBy: fn (Model $subscription): string => PlanPrice::decimal(self::version($subscription)))
            ->field('currency_code', columns: [], relations: ['planVersion'], computedBy: fn (Model $subscription): string => self::version($subscription)->currency_code)
            ->field('billing_interval_months', columns: [], relations: ['planVersion'], computedBy: fn (Model $subscription): int => self::version($subscription)->billing_interval_months)
            ->field('starts_at')
            ->field('ends_at')
            ->field('grace_ends_at', columns: ['ends_at'], computedBy: fn (Model $subscription): CarbonImmutable => self::subscription($subscription)->graceEndsAt())
            ->include('next', self::class);
    }

    private static function subscription(Model $subscription): Subscription
    {
        assert($subscription instanceof Subscription);

        return $subscription;
    }

    private static function version(Model $subscription): PlanVersion
    {
        $version = $subscription->getRelation('planVersion');
        assert($version instanceof PlanVersion);

        return $version;
    }
}

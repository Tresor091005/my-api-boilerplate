<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Billing\Http\Resources\Fields\PlanPrice;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class CheckoutResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('action')
            ->field('status')
            ->field('plan_id', columns: [], relations: ['planVersion'], computedBy: fn (Model $checkout): string => self::version($checkout)->plan_id)
            ->field('plan_name', columns: [], relations: ['planVersion.plan'], computedBy: fn (Model $checkout): string => self::version($checkout)->plan->name)
            ->field('periods')
            ->field('total_amount', columns: ['total_amount', 'currency_code'], computedBy: fn (Model $checkout): string => PlanPrice::amount((int) $checkout->getAttribute('total_amount'), (string) $checkout->getAttribute('currency_code')))
            ->field('currency_code')
            ->field('covers_from')
            ->field('covers_until')
            ->field('started_at')
            ->field('expires_at')
            ->field('confirmed_at');
    }

    private static function version(Model $checkout): PlanVersion
    {
        $version = $checkout->getRelation('planVersion');
        assert($version instanceof PlanVersion);

        return $version;
    }
}

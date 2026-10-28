<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Billing\Http\Resources\Fields\PlanPrice;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class PlanVersionResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('version')
            ->field('status')
            ->field('price', columns: ['price', 'currency_code'], computedBy: fn (Model $version): string => PlanPrice::decimal($version))
            ->field('currency_code')
            ->field('billing_interval')
            ->field('published_at')
            ->field('subscribers_count', columns: [])
            ->include('features', PlanFeatureResource::class);
    }
}

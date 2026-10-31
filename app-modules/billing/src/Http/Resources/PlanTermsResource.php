<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Billing\Http\Resources\Fields\PlanPrice;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class PlanTermsResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('price', columns: ['price', 'currency_code'], computedBy: fn (Model $version): string => PlanPrice::decimal($version))
            ->field('currency_code')
            ->field('billing_interval_months')
            ->field('published_at')
            ->field('updated_at')
            ->include('features', PlanFeatureResource::class);
    }
}

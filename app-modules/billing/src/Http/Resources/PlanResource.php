<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Billing\Enums\PlanStatus;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class PlanResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('code')
            ->field('name')
            ->field('is_trial')
            ->field('status', columns: [], computedBy: fn (Model $plan): string => PlanStatus::of(
                (bool) $plan->getAttribute('is_on_sale'),
                (bool) $plan->getAttribute('has_been_subscribed'),
            )->value)
            ->field('created_at')
            ->field('updated_at')
            ->include('current', PlanTermsResource::class, relation: 'currentVersion')
            ->include('draft', PlanTermsResource::class, relation: 'draftVersion');
    }
}

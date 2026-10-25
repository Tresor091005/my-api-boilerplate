<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class OrganizationSettingsResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name', relations: ['organization'], computedBy: fn (Model $settings): string => $settings->getRelation('organization')->getAttribute('name'))
            ->field('functional_currency_code', relations: ['organization'], computedBy: fn (Model $settings): string => $settings->getRelation('organization')->getAttribute('functional_currency_code'))
            ->field('enable_currencies')
            ->field('timezone')
            ->field('created_at')
            ->field('updated_at');
    }
}

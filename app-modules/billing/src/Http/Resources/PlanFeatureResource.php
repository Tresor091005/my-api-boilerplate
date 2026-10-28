<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Billing\Registries\FeatureRegistry;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class PlanFeatureResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('key', columns: ['feature_key'], computedBy: fn (Model $grant): string => (string) $grant->getAttribute('feature_key'))
            ->field('type', columns: ['feature_type'], computedBy: fn (Model $grant): string => $grant->getAttribute('feature_type')->value)
            ->field('name', columns: ['feature_key'], computedBy: fn (Model $grant): string => __(
                app(FeatureRegistry::class)->get((string) $grant->getAttribute('feature_key'))->translationKey(),
            ))
            ->field('allowance');
    }
}

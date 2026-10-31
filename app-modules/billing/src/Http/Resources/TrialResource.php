<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class TrialResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('plan_name', columns: [], relations: ['planVersion.plan'], computedBy: fn (Model $trial): string => self::version($trial)->plan->name)
            ->field('starts_at')
            ->field('ends_at');
    }

    private static function version(Model $trial): PlanVersion
    {
        $version = $trial->getRelation('planVersion');
        assert($version instanceof PlanVersion);

        return $version;
    }
}

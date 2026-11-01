<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class TaxResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('code')
            ->field('name')
            ->field('kind')
            ->field('calculation_type')
            ->field('fixed_mode')
            ->field('tax_unit_code', columns: ['tax_unit_id'], relations: ['taxUnit'], computedBy: fn (Model $tax): ?string => $tax->getRelation('taxUnit')?->getAttribute('code'))
            ->field('base_type')
            ->field('is_progressive')
            ->field('bracket_mode')
            ->field('rounding_mode')
            ->field('is_active')
            ->field('created_at')
            ->field('updated_at')
            ->include('brackets', TaxBracketResource::class)
            ->include('dependencies', TaxDependencyResource::class);
    }
}

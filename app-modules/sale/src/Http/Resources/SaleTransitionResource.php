<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * The member is given by id only; who they are is read from the members.
 */
class SaleTransitionResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('event')
            ->field('from_status')
            ->field('to_status')
            ->field('member_id')
            ->field('reason')
            ->field('created_at');
    }
}

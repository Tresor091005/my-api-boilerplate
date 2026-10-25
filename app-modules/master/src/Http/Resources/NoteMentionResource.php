<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class NoteMentionResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('member_id')
            ->field('mentioned_at')
            ->field('read_at');
    }
}

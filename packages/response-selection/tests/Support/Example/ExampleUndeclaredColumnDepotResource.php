<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * Wrong on purpose: `label` reads `name` but declares `code` only. Selected with `name`, the include
 * loads the column for it; selected alone, the include is narrowed to `code` and `name` is missing.
 */
final class ExampleUndeclaredColumnDepotResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('label', columns: ['code'], computedBy: fn (Model $depot): string => "{$depot->getAttribute('code')} {$depot->getAttribute('name')}");
    }
}

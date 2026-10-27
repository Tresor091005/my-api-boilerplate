<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class PermissionResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('title', columns: ['name'], computedBy: fn (Model $permission): string => __('iam::console.permissions.title', [
                'action' => ucfirst(self::action($permission)),
                'model'  => self::modelAlias($permission),
            ]))
            ->field('description', columns: ['name'], computedBy: fn (Model $permission): string => __('iam::console.permissions.description', [
                'action' => self::action($permission),
                'model'  => self::modelAlias($permission),
            ]));
    }

    private static function modelAlias(Model $permission): string
    {
        return Str::before((string) $permission->getAttribute('name'), '.');
    }

    private static function action(Model $permission): string
    {
        return Str::after((string) $permission->getAttribute('name'), '.');
    }
}

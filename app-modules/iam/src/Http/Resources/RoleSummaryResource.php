<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * A role shown under an invitation or a member role: its permissions are read through the role routes only.
 */
class RoleSummaryResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('description', columns: ['name', 'description', 'is_builtin'], computedBy: fn (Model $role): ?string => self::description($role))
            ->field('is_builtin')
            ->field('is_active');
    }

    /**
     * A built-in role is described in the current locale; any other role keeps the description its organization wrote.
     */
    public static function description(Model $role): ?string
    {
        $builtinRole = $role->getAttribute('is_builtin') ? SysRole::tryFrom((string) $role->getAttribute('name')) : null;

        return $builtinRole?->description() ?? $role->getAttribute('description');
    }
}

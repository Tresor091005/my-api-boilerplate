<?php

declare(strict_types=1);

namespace Lahatre\Iam\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lahatre\Shared\Traits\SharedTraits;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string $id
 * @property string|null $team_id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_builtin
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 *
 * @method static Builder<static>|Role newModelQuery()
 * @method static Builder<static>|Role newQuery()
 * @method static Builder<static>|Role permission($permissions, bool $without = false)
 * @method static Builder<static>|Role query()
 * @method static Builder<static>|Role whereCreatedAt($value)
 * @method static Builder<static>|Role whereDescription($value)
 * @method static Builder<static>|Role whereGuardName($value)
 * @method static Builder<static>|Role whereId($value)
 * @method static Builder<static>|Role whereIsBuiltin($value)
 * @method static Builder<static>|Role whereName($value)
 * @method static Builder<static>|Role whereTeamId($value)
 * @method static Builder<static>|Role whereUpdatedAt($value)
 * @method static Builder<static>|Role withoutPermission($permissions)
 *
 * @mixin \Eloquent
 */
class Role extends SpatieRole
{
    use SharedTraits;

    protected $table = 'iam_roles';

    protected $fillable = [
        'name',
        'is_builtin',
        'description',
        'guard_name',
    ];

    protected $casts = [
        'id'          => 'string',
        'name'        => 'string',
        'is_builtin'  => 'boolean',
        'description' => 'string',
        'guard_name'  => 'string',
        'created_at'  => 'immutable_datetime',
        'updated_at'  => 'immutable_datetime',
    ];
}

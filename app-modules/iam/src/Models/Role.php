<?php

declare(strict_types=1);

namespace Lahatre\Iam\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Iam\Database\Factories\RoleFactory;
use Lahatre\Shared\Traits\SharedTraits;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string $id
 * @property string|null $team_id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_builtin
 * @property bool $is_active
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
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
 * @method static RoleFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Role extends SpatieRole
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'iam_roles';

    protected $fillable = [
        'team_id',
        'name',
        'is_builtin',
        'is_active',
        'description',
        'guard_name',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'id'          => 'string',
        'team_id'     => 'string',
        'name'        => 'string',
        'is_builtin'  => 'boolean',
        'is_active'   => 'boolean',
        'description' => 'string',
        'guard_name'  => 'string',
        'created_at'  => 'immutable_datetime',
        'updated_at'  => 'immutable_datetime',
        'deleted_at'  => 'immutable_datetime',
    ];
}

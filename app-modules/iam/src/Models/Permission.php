<?php

declare(strict_types=1);

namespace Lahatre\Iam\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lahatre\Shared\Traits\SharedTraits;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property string $id
 * @property string $name
 * @property string|null $title
 * @property string|null $description
 * @property string $guard_name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 *
 * @method static Builder<static>|Permission newModelQuery()
 * @method static Builder<static>|Permission newQuery()
 * @method static Builder<static>|Permission permission($permissions, bool $without = false)
 * @method static Builder<static>|Permission query()
 * @method static Builder<static>|Permission role($roles, ?string $guard = null, bool $without = false)
 * @method static Builder<static>|Permission whereCreatedAt($value)
 * @method static Builder<static>|Permission whereDescription($value)
 * @method static Builder<static>|Permission whereGuardName($value)
 * @method static Builder<static>|Permission whereId($value)
 * @method static Builder<static>|Permission whereName($value)
 * @method static Builder<static>|Permission whereTitle($value)
 * @method static Builder<static>|Permission whereUpdatedAt($value)
 * @method static Builder<static>|Permission withoutPermission($permissions)
 * @method static Builder<static>|Permission withoutRole($roles, ?string $guard = null)
 *
 * @mixin \Eloquent
 */
class Permission extends SpatiePermission
{
    use SharedTraits;

    protected $table = 'iam_permissions';

    protected $fillable = [
        'name',
        'title',
        'description',
        'guard_name',
    ];

    protected $casts = [
        'id'          => 'string',
        'name'        => 'string',
        'title'       => 'string',
        'description' => 'string',
        'guard_name'  => 'string',
        'created_at'  => 'immutable_datetime',
        'updated_at'  => 'immutable_datetime',
    ];
}

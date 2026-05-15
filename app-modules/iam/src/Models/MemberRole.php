<?php

declare(strict_types=1);

namespace Lahatre\Iam\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Iam\Database\Factories\MemberRoleFactory;
use Lahatre\Shared\Traits\SharedTraits;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $member_id
 * @property string $role_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read OrganizationMember $organizationMember
 * @property-read Role $role
 *
 * @method static Builder<static>|MemberRole newModelQuery()
 * @method static Builder<static>|MemberRole newQuery()
 * @method static Builder<static>|MemberRole query()
 * @method static Builder<static>|MemberRole whereCreatedAt($value)
 * @method static Builder<static>|MemberRole whereId($value)
 * @method static Builder<static>|MemberRole whereMemberId($value)
 * @method static Builder<static>|MemberRole whereRoleId($value)
 * @method static Builder<static>|MemberRole whereUpdatedAt($value)
 *
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 *
 * @method static Builder<static>|MemberRole permission($permissions, bool $without = false)
 * @method static Builder<static>|MemberRole role($roles, ?string $guard = null, bool $without = false)
 * @method static Builder<static>|MemberRole withoutPermission($permissions)
 * @method static Builder<static>|MemberRole withoutRole($roles, ?string $guard = null)
 * @method static Builder<static>|MemberRole whereOrganizationId($value)
 * @method static MemberRoleFactory factory($count = null, $state = [])
 * @method static Builder<static>|MemberRole onlyTrashed()
 * @method static Builder<static>|MemberRole withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|MemberRole withoutTrashed()
 *
 * @property CarbonImmutable|null $deleted_at
 *
 * @method static Builder<static>|MemberRole whereDeletedAt($value)
 *
 * @mixin \Eloquent
 */
class MemberRole extends Model
{
    use HasRoles;
    use SharedTraits;
    use SoftDeletes;

    protected string $guard_name = 'sanctum';

    protected $table = 'iam_member_roles';

    protected $fillable = [
        'organization_id',
        'member_id',
        'role_id',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'member_id'       => 'string',
        'role_id'         => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    protected function getDefaultGuardName(): string
    {
        return $this->guard_name;
    }

    public function organizationMember(): BelongsTo
    {
        return $this->belongsTo(OrganizationMember::class, 'member_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}

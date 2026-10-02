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
use Lahatre\Shared\Models\Authenticatable;
use Lahatre\Shared\Traits\SharedTraits;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $member_id
 * @property string $role_id
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read OrganizationMember $organizationMember
 * @property-read Role $role
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 *
 * @method static Builder<static>|MemberRole newModelQuery()
 * @method static Builder<static>|MemberRole newQuery()
 * @method static Builder<static>|MemberRole query()
 * @method static Builder<static>|MemberRole whereCreatedAt($value)
 * @method static Builder<static>|MemberRole whereId($value)
 * @method static Builder<static>|MemberRole whereMemberId($value)
 * @method static Builder<static>|MemberRole whereRoleId($value)
 * @method static Builder<static>|MemberRole whereUpdatedAt($value)
 * @method static Builder<static>|MemberRole permission($permissions, bool $without = false)
 * @method static Builder<static>|MemberRole withoutPermission($permissions)
 * @method static Builder<static>|MemberRole withoutRole($roles, ?string $guard = null)
 * @method static Builder<static>|MemberRole whereOrganizationId($value)
 * @method static MemberRoleFactory factory($count = null, $state = [])
 * @method static Builder<static>|MemberRole onlyTrashed()
 * @method static Builder<static>|MemberRole withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|MemberRole withoutTrashed()
 * @method static Builder<static>|MemberRole whereDeletedAt($value)
 * @method static Builder<static>|MemberRole team($teams, bool $without = false)
 * @method static Builder<static>|MemberRole withoutTeam($teams)
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
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'member_id'       => 'string',
        'role_id'         => 'string',
        'is_active'       => 'boolean',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    protected function getDefaultGuardName(): string
    {
        return $this->guard_name;
    }

    /** Validate already resolved access records without loading relations. */
    public function hasValidContextFor(Authenticatable $user, ?OrganizationMember $member, ?Role $role): bool
    {
        if ($member === null || $role === null || !$member->relationLoaded('organization')) {
            return false;
        }

        $organization = $member->organization;

        return $organization !== null && !$organization->trashed()
            && !$this->trashed() && !$member->trashed() && !$role->trashed()
            && $this->is_active && $member->is_active && $role->is_active
            && $member->id === $this->member_id && $role->id === $this->role_id
            && $member->user_id === $user->id
            && $member->organization_id === $this->organization_id
            && $organization->id === $this->organization_id
            && ($role->team_id === null || $role->team_id === $this->organization_id)
            && $role->guard_name === config('auth.defaults.guard');
    }

    public function organizationMember(): BelongsTo
    {
        /** Callers must enforce organization authorization when using this cross-organization relation. */
        return $this->belongsTo(OrganizationMember::class, 'member_id');
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}

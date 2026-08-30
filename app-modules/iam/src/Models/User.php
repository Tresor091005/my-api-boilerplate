<?php

declare(strict_types=1);

namespace Lahatre\Iam\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Database\Factories\UserFactory;
use Lahatre\Shared\Models\Authenticatable;

/**
 * @property string $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, MemberRole> $memberRoles
 * @property-read int|null $member_roles_count
 * @property-read Collection<int, OrganizationMember> $organizationMemberships
 * @property-read int|null $organization_memberships_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User permission($permissions, bool $without = false)
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereFirstName($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereLastName($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User withoutPermission($permissions)
 * @method static Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User onlyTrashed()
 * @method static Builder<static>|User whereDeletedAt($value)
 * @method static Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|User withoutTrashed()
 * @method static Builder<static>|User team($teams, bool $without = false)
 * @method static Builder<static>|User withoutTeam($teams)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'iam_users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'id'                => 'string',
            'first_name'        => 'string',
            'last_name'         => 'string',
            'email'             => 'string',
            'email_verified_at' => 'immutable_datetime',
            'password'          => 'hashed',
            'remember_token'    => 'string',
            'created_at'        => 'immutable_datetime',
            'updated_at'        => 'immutable_datetime',
            'deleted_at'        => 'immutable_datetime',
        ];
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class, 'user_id')
            ->where('iam_organization_members.organization_id', currentOrganizationId());
    }
}

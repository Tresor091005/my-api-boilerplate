<?php

declare(strict_types=1);

namespace Lahatre\Iam\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lahatre\Iam\Database\Factories\OrganizationMemberFactory;
use Lahatre\Organization\Models\Organization;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $user_id
 * @property string $organization_id
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, MemberRole> $memberRoles
 * @property-read int|null $member_roles_count
 * @property-read Collection<int, MemberRole> $organizationMemberRoles
 * @property-read User $user
 * @property-read Organization|null $organization
 *
 * @method static Builder<static>|OrganizationMember newModelQuery()
 * @method static Builder<static>|OrganizationMember newQuery()
 * @method static Builder<static>|OrganizationMember query()
 * @method static Builder<static>|OrganizationMember whereCreatedAt($value)
 * @method static Builder<static>|OrganizationMember whereId($value)
 * @method static Builder<static>|OrganizationMember whereOrganizationId($value)
 * @method static Builder<static>|OrganizationMember whereUpdatedAt($value)
 * @method static Builder<static>|OrganizationMember whereUserId($value)
 * @method static OrganizationMemberFactory factory($count = null, $state = [])
 * @method static Builder<static>|OrganizationMember onlyTrashed()
 * @method static Builder<static>|OrganizationMember withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|OrganizationMember withoutTrashed()
 * @method static Builder<static>|OrganizationMember whereDeletedAt($value)
 *
 * @mixin \Eloquent
 */
class OrganizationMember extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'iam_organization_members';

    protected $fillable = [
        'user_id',
        'organization_id',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $casts = [
        'id'              => 'string',
        'user_id'         => 'string',
        'organization_id' => 'string',
        'is_active'       => 'boolean',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function memberRoles(): HasMany
    {
        /** Callers must enforce organization authorization when using this cross-organization relation. */
        return $this->hasMany(MemberRole::class, 'member_id');
    }

    /**
     * The member's assignments in its own organization, the only ones API responses show.
     *
     * @return HasMany<MemberRole, $this>
     */
    public function organizationMemberRoles(): HasMany
    {
        return $this->hasMany(MemberRole::class, 'member_id')->whereExists(fn (QueryBuilder $query) => $query->selectRaw('1')
            ->from('iam_organization_members as assignment_member')
            ->whereColumn('assignment_member.id', 'iam_member_roles.member_id')
            ->whereColumn('assignment_member.organization_id', 'iam_member_roles.organization_id'));
    }
}

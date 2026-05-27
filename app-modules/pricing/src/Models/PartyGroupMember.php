<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lahatre\Pricing\Database\Factories\PartyGroupMemberFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $group_id
 * @property string $party_type
 * @property string $party_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|PartyGroupMember newModelQuery()
 * @method static Builder<static>|PartyGroupMember newQuery()
 * @method static Builder<static>|PartyGroupMember query()
 * @method static Builder<static>|PartyGroupMember whereCreatedAt($value)
 * @method static Builder<static>|PartyGroupMember whereGroupId($value)
 * @method static Builder<static>|PartyGroupMember whereId($value)
 * @method static Builder<static>|PartyGroupMember whereOrganizationId($value)
 * @method static Builder<static>|PartyGroupMember wherePartyId($value)
 * @method static Builder<static>|PartyGroupMember wherePartyType($value)
 * @method static Builder<static>|PartyGroupMember whereUpdatedAt($value)
 * @method static PartyGroupMemberFactory factory($count = null, $state = [])
 *
 * @property-read PartyGroup $group
 * @property-read Model $party
 *
 * @mixin \Eloquent
 */
class PartyGroupMember extends Model
{
    use SharedTraits;

    protected $table = 'pricing_party_group_members';

    protected $fillable = [
        'organization_id',
        'group_id',
        'party_type',
        'party_id',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'group_id'        => 'string',
        'party_type'      => 'string',
        'party_id'        => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PartyGroup::class, 'group_id', 'id');
    }

    public function party(): MorphTo
    {
        return $this->morphTo('party');
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Pricing\Database\Factories\PartyGroupFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 *
 * @method static Builder<static>|PartyGroup newModelQuery()
 * @method static Builder<static>|PartyGroup newQuery()
 * @method static Builder<static>|PartyGroup onlyTrashed()
 * @method static Builder<static>|PartyGroup query()
 * @method static Builder<static>|PartyGroup whereCreatedAt($value)
 * @method static Builder<static>|PartyGroup whereDeletedAt($value)
 * @method static Builder<static>|PartyGroup whereDescription($value)
 * @method static Builder<static>|PartyGroup whereId($value)
 * @method static Builder<static>|PartyGroup whereIsActive($value)
 * @method static Builder<static>|PartyGroup whereMetadata($value)
 * @method static Builder<static>|PartyGroup whereName($value)
 * @method static Builder<static>|PartyGroup whereOrganizationId($value)
 * @method static Builder<static>|PartyGroup whereUpdatedAt($value)
 * @method static Builder<static>|PartyGroup withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|PartyGroup withoutTrashed()
 * @method static PartyGroupFactory factory($count = null, $state = [])
 *
 * @property-read Collection<int, PartyGroupMember> $members
 * @property-read int|null $members_count
 * @property-read Collection<int, PriceEntry> $priceEntries
 * @property-read int|null $price_entries_count
 *
 * @mixin \Eloquent
 */
class PartyGroup extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'pricing_party_groups';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'name'            => 'string',
        'description'     => 'string',
        'is_active'       => 'boolean',
        'metadata'        => 'array',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(PartyGroupMember::class, 'group_id', 'id');
    }

    public function priceEntries(): MorphMany
    {
        return $this->morphMany(PriceEntry::class, 'party');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPricingPartyGroupSummary(): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
        ];
    }
}

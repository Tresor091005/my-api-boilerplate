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
use Lahatre\Pricing\Database\Factories\PriceableGroupFactory;
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
 * @method static Builder<static>|PriceableGroup newModelQuery()
 * @method static Builder<static>|PriceableGroup newQuery()
 * @method static Builder<static>|PriceableGroup onlyTrashed()
 * @method static Builder<static>|PriceableGroup query()
 * @method static Builder<static>|PriceableGroup whereCreatedAt($value)
 * @method static Builder<static>|PriceableGroup whereDeletedAt($value)
 * @method static Builder<static>|PriceableGroup whereDescription($value)
 * @method static Builder<static>|PriceableGroup whereId($value)
 * @method static Builder<static>|PriceableGroup whereIsActive($value)
 * @method static Builder<static>|PriceableGroup whereMetadata($value)
 * @method static Builder<static>|PriceableGroup whereName($value)
 * @method static Builder<static>|PriceableGroup whereOrganizationId($value)
 * @method static Builder<static>|PriceableGroup whereUpdatedAt($value)
 * @method static Builder<static>|PriceableGroup withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|PriceableGroup withoutTrashed()
 * @method static PriceableGroupFactory factory($count = null, $state = [])
 *
 * @property-read Collection<int, PriceableGroupMember> $members
 * @property-read int|null $members_count
 * @property-read Collection<int, PriceEntry> $priceEntries
 * @property-read int|null $price_entries_count
 *
 * @mixin \Eloquent
 */
class PriceableGroup extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'pricing_priceable_groups';

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
        return $this->hasMany(PriceableGroupMember::class, 'group_id', 'id');
    }

    public function priceEntries(): MorphMany
    {
        return $this->morphMany(PriceEntry::class, 'priceable');
    }

    /**
     * @return array<string, mixed>
     */
    public function toPriceableGroupSummary(): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
        ];
    }
}

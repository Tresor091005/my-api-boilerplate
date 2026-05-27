<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lahatre\Pricing\Database\Factories\PriceableGroupMemberFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $group_id
 * @property string $priceable_type
 * @property string $priceable_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|PriceableGroupMember newModelQuery()
 * @method static Builder<static>|PriceableGroupMember newQuery()
 * @method static Builder<static>|PriceableGroupMember query()
 * @method static Builder<static>|PriceableGroupMember whereCreatedAt($value)
 * @method static Builder<static>|PriceableGroupMember whereGroupId($value)
 * @method static Builder<static>|PriceableGroupMember whereId($value)
 * @method static Builder<static>|PriceableGroupMember whereOrganizationId($value)
 * @method static Builder<static>|PriceableGroupMember wherePriceableId($value)
 * @method static Builder<static>|PriceableGroupMember wherePriceableType($value)
 * @method static Builder<static>|PriceableGroupMember whereUpdatedAt($value)
 * @method static PriceableGroupMemberFactory factory($count = null, $state = [])
 *
 * @property-read PriceableGroup $group
 * @property-read Model $priceable
 *
 * @mixin \Eloquent
 */
class PriceableGroupMember extends Model
{
    use SharedTraits;

    protected $table = 'pricing_priceable_group_members';

    protected $fillable = [
        'organization_id',
        'group_id',
        'priceable_type',
        'priceable_id',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'group_id'        => 'string',
        'priceable_type'  => 'string',
        'priceable_id'    => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PriceableGroup::class, 'group_id', 'id');
    }

    public function priceable(): MorphTo
    {
        return $this->morphTo('priceable');
    }
}

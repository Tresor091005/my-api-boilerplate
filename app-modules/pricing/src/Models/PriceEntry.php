<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Pricing\Database\Factories\PriceEntryFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $priceable_type
 * @property string $priceable_id
 * @property string $priceable_kind
 * @property string|null $party_type
 * @property string|null $party_id
 * @property string|null $party_kind
 * @property string $context
 * @property string $currency_code
 * @property string $unit_code
 * @property int $min_quantity
 * @property int|null $max_quantity
 * @property int $unit_price
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 *
 * @method static Builder<static>|PriceEntry newModelQuery()
 * @method static Builder<static>|PriceEntry newQuery()
 * @method static Builder<static>|PriceEntry onlyTrashed()
 * @method static Builder<static>|PriceEntry query()
 * @method static Builder<static>|PriceEntry whereContext($value)
 * @method static Builder<static>|PriceEntry whereCreatedAt($value)
 * @method static Builder<static>|PriceEntry whereCurrencyCode($value)
 * @method static Builder<static>|PriceEntry whereDeletedAt($value)
 * @method static Builder<static>|PriceEntry whereEndsAt($value)
 * @method static Builder<static>|PriceEntry whereId($value)
 * @method static Builder<static>|PriceEntry whereIsActive($value)
 * @method static Builder<static>|PriceEntry whereMaxQuantity($value)
 * @method static Builder<static>|PriceEntry whereMetadata($value)
 * @method static Builder<static>|PriceEntry whereMinQuantity($value)
 * @method static Builder<static>|PriceEntry whereOrganizationId($value)
 * @method static Builder<static>|PriceEntry wherePartyId($value)
 * @method static Builder<static>|PriceEntry wherePartyKind($value)
 * @method static Builder<static>|PriceEntry wherePartyType($value)
 * @method static Builder<static>|PriceEntry wherePriceableId($value)
 * @method static Builder<static>|PriceEntry wherePriceableKind($value)
 * @method static Builder<static>|PriceEntry wherePriceableType($value)
 * @method static Builder<static>|PriceEntry whereStartsAt($value)
 * @method static Builder<static>|PriceEntry whereUnitPrice($value)
 * @method static Builder<static>|PriceEntry whereUnitCode($value)
 * @method static Builder<static>|PriceEntry whereUpdatedAt($value)
 * @method static Builder<static>|PriceEntry withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|PriceEntry withoutTrashed()
 * @method static PriceEntryFactory factory($count = null, $state = [])
 *
 * @property-read Model|null $party
 * @property-read Model $priceable
 *
 * @mixin \Eloquent
 */
class PriceEntry extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'pricing_price_entries';

    protected $fillable = [
        'organization_id',
        'priceable_type',
        'priceable_id',
        'priceable_kind',
        'party_type',
        'party_id',
        'party_kind',
        'context',
        'currency_code',
        'unit_code',
        'min_quantity',
        'max_quantity',
        'unit_price',
        'starts_at',
        'ends_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'priceable_type'  => 'string',
        'priceable_id'    => 'string',
        'priceable_kind'  => 'string',
        'party_type'      => 'string',
        'party_id'        => 'string',
        'party_kind'      => 'string',
        'context'         => 'string',
        'currency_code'   => 'string',
        'unit_code'       => 'string',
        'min_quantity'    => 'integer',
        'max_quantity'    => 'integer',
        'unit_price'      => 'integer',
        'starts_at'       => 'immutable_datetime',
        'ends_at'         => 'immutable_datetime',
        'is_active'       => 'boolean',
        'metadata'        => 'array',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function priceable(): MorphTo
    {
        return $this->morphTo('priceable');
    }

    public function party(): MorphTo
    {
        return $this->morphTo('party');
    }
}

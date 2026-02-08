<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $priceable_type
 * @property string $priceable_id
 * @property string $currency_code
 * @property int $min_quantity
 * @property int|null $max_quantity
 * @property int $step
 * @property int $amount
 * @property bool $is_active
 * @property CarbonImmutable|null $active_from
 * @property CarbonImmutable|null $active_to
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Currency $currency
 * @property-read Model|\Eloquent $priceable
 *
 * @method static Builder<static>|Price newModelQuery()
 * @method static Builder<static>|Price newQuery()
 * @method static Builder<static>|Price query()
 * @method static Builder<static>|Price whereActiveFrom($value)
 * @method static Builder<static>|Price whereActiveTo($value)
 * @method static Builder<static>|Price whereAmount($value)
 * @method static Builder<static>|Price whereCreatedAt($value)
 * @method static Builder<static>|Price whereCurrencyCode($value)
 * @method static Builder<static>|Price whereId($value)
 * @method static Builder<static>|Price whereIsActive($value)
 * @method static Builder<static>|Price whereMaxQuantity($value)
 * @method static Builder<static>|Price whereMinQuantity($value)
 * @method static Builder<static>|Price wherePriceableId($value)
 * @method static Builder<static>|Price wherePriceableType($value)
 * @method static Builder<static>|Price whereStep($value)
 * @method static Builder<static>|Price whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Price extends Model
{
    use SharedTraits;

    protected $table = 'catalog_prices';

    protected $fillable = [
        'priceable_type',
        'priceable_id',
        'currency_code',
        'min_quantity',
        'max_quantity',
        'step',
        'amount',
        'is_active',
        'active_from',
        'active_to',
    ];

    protected $casts = [
        'id'             => 'string',
        'priceable_type' => 'string',
        'priceable_id'   => 'string',
        'currency_code'  => 'string',
        'min_quantity'   => 'integer',
        'max_quantity'   => 'integer',
        'step'           => 'integer',
        'amount'         => 'integer',
        'is_active'      => 'boolean',
        'active_from'    => 'immutable_datetime',
        'active_to'      => 'immutable_datetime',
        'created_at'     => 'immutable_datetime',
        'updated_at'     => 'immutable_datetime',
    ];

    public function priceable(): MorphTo
    {
        return $this->morphTo('priceable', 'priceable_type', 'priceable_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\PriceLevelFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * A named list of prices ("retail", "wholesale") in one currency, fixed at creation.
 * One level per organization is built in: it can be renamed, never deleted.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string $currency_code
 * @property bool $is_builtin
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 *
 * @method static Builder<static>|PriceLevel newModelQuery()
 * @method static Builder<static>|PriceLevel newQuery()
 * @method static Builder<static>|PriceLevel query()
 * @method static Builder<static>|PriceLevel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|PriceLevel onlyTrashed()
 * @method static Builder<static>|PriceLevel withoutTrashed()
 * @method static PriceLevelFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class PriceLevel extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_price_levels';

    protected $fillable = [
        'organization_id',
        'name',
        'currency_code',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'name'            => 'string',
        'currency_code'   => 'string',
        'is_builtin'      => 'boolean',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];
}

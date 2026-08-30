<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Master\Database\Factories\CurrencyFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $precision
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 *
 * @method static Builder<static>|Currency newModelQuery()
 * @method static Builder<static>|Currency newQuery()
 * @method static Builder<static>|Currency query()
 * @method static Builder<static>|Currency whereCode($value)
 * @method static Builder<static>|Currency whereCreatedAt($value)
 * @method static Builder<static>|Currency whereId($value)
 * @method static Builder<static>|Currency whereName($value)
 * @method static Builder<static>|Currency wherePrecision($value)
 * @method static Builder<static>|Currency whereSymbol($value)
 * @method static Builder<static>|Currency whereUpdatedAt($value)
 * @method static CurrencyFactory factory($count = null, $state = [])
 * @method static Builder<static>|Currency onlyTrashed()
 * @method static Builder<static>|Currency withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Currency withoutTrashed()
 * @method static Builder<static>|Currency whereDeletedAt($value)
 *
 * @mixin \Eloquent
 */
class Currency extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'master_currencies';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'precision',
    ];

    protected $casts = [
        'id'         => 'string',
        'code'       => 'string',
        'name'       => 'string',
        'symbol'     => 'string',
        'precision'  => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'deleted_at' => 'immutable_datetime',
    ];
}

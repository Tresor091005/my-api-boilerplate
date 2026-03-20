<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
 *
 * @mixin \Eloquent
 */
class Currency extends Model
{
    use SharedTraits;

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
    ];
}

<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Master\Database\Factories\UnitFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $symbol
 * @property int $ratio
 * @property string $group_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read UnitGroup $group
 *
 * @method static Builder<static>|Unit newModelQuery()
 * @method static Builder<static>|Unit newQuery()
 * @method static Builder<static>|Unit query()
 * @method static Builder<static>|Unit whereCode($value)
 * @method static Builder<static>|Unit whereCreatedAt($value)
 * @method static Builder<static>|Unit whereDeletedAt($value)
 * @method static Builder<static>|Unit whereId($value)
 * @method static Builder<static>|Unit whereName($value)
 * @method static Builder<static>|Unit whereRatio($value)
 * @method static Builder<static>|Unit whereSymbol($value)
 * @method static Builder<static>|Unit whereGroupId($value)
 * @method static Builder<static>|Unit whereUpdatedAt($value)
 * @method static UnitFactory factory($count = null, $state = [])
 * @method static Builder<static>|Unit onlyTrashed()
 * @method static Builder<static>|Unit withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Unit withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Unit extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'master_units';

    protected $fillable = [
        'code',
        'name',
        'ratio',
        'symbol',
        'group_id',
    ];

    protected $casts = [
        'id'         => 'string',
        'code'       => 'string',
        'name'       => 'string',
        'ratio'      => 'integer',
        'symbol'     => 'string',
        'group_id'   => 'string',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'deleted_at' => 'immutable_datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(UnitGroup::class, 'group_id');
    }
}

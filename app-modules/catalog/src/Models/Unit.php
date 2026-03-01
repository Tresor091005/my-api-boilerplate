<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Catalog\Database\Factories\UnitFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $symbol
 * @property int $ratio
 * @property string $unit_group
 * @property bool $is_builtin
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|Unit newModelQuery()
 * @method static Builder<static>|Unit newQuery()
 * @method static Builder<static>|Unit query()
 * @method static Builder<static>|Unit whereCode($value)
 * @method static Builder<static>|Unit whereCreatedAt($value)
 * @method static Builder<static>|Unit whereId($value)
 * @method static Builder<static>|Unit whereName($value)
 * @method static Builder<static>|Unit whereRatio($value)
 * @method static Builder<static>|Unit whereSymbol($value)
 * @method static Builder<static>|Unit whereUnitGroup($value)
 * @method static Builder<static>|Unit whereIsBuiltin($value)
 * @method static Builder<static>|Unit whereUpdatedAt($value)
 * @method static Builder<static>|Unit whereIsActive($value)
 * @method static UnitFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Unit extends Model
{
    use SharedTraits;

    protected $table = 'catalog_units';

    protected $fillable = [
        'code',
        'name',
        'ratio',
        'symbol',
        'unit_group',
        'is_builtin',
        'is_active',
    ];

    protected $casts = [
        'id'         => 'string',
        'code'       => 'string',
        'name'       => 'string',
        'ratio'      => 'integer',
        'symbol'     => 'string',
        'unit_group' => 'string',
        'is_builtin' => 'boolean',
        'is_active'  => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    protected static function newFactory(): UnitFactory
    {
        return UnitFactory::new();
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Master\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Master\Database\Factories\UnitGroupFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $name
 * @property bool $is_builtin
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Unit> $units
 * @property-read Unit|null $baseUnit
 *
 * @method static Builder<static>|UnitGroup newModelQuery()
 * @method static Builder<static>|UnitGroup newQuery()
 * @method static Builder<static>|UnitGroup query()
 * @method static Builder<static>|UnitGroup whereCreatedAt($value)
 * @method static Builder<static>|UnitGroup whereDeletedAt($value)
 * @method static Builder<static>|UnitGroup whereId($value)
 * @method static Builder<static>|UnitGroup whereName($value)
 * @method static Builder<static>|UnitGroup whereIsBuiltin($value)
 * @method static Builder<static>|UnitGroup whereUpdatedAt($value)
 * @method static UnitGroupFactory factory($count = null, $state = [])
 *
 * @property-read int|null $units_count
 *
 * @method static Builder<static>|UnitGroup onlyTrashed()
 * @method static Builder<static>|UnitGroup withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|UnitGroup withoutTrashed()
 *
 * @mixin \Eloquent
 */
class UnitGroup extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'master_unit_groups';

    protected $fillable = [
        'name',
        'is_builtin',
    ];

    protected $casts = [
        'id'         => 'string',
        'name'       => 'string',
        'is_builtin' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'deleted_at' => 'immutable_datetime',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'group_id');
    }

    public function baseUnit(): HasOne
    {
        return $this->hasOne(Unit::class, 'group_id')
            ->where('ratio', 1);
    }

    protected static function newFactory(): UnitGroupFactory
    {
        return UnitGroupFactory::new();
    }
}

<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property int $base_ratio
 * @property bool $is_base
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|Unit newModelQuery()
 * @method static Builder<static>|Unit newQuery()
 * @method static Builder<static>|Unit query()
 * @method static Builder<static>|Unit whereBaseRatio($value)
 * @method static Builder<static>|Unit whereCode($value)
 * @method static Builder<static>|Unit whereCreatedAt($value)
 * @method static Builder<static>|Unit whereId($value)
 * @method static Builder<static>|Unit whereIsBase($value)
 * @method static Builder<static>|Unit whereName($value)
 * @method static Builder<static>|Unit whereUpdatedAt($value)
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
        'base_ratio',
        'is_base',
    ];

    protected $casts = [
        'id'         => 'string',
        'code'       => 'string',
        'name'       => 'string',
        'base_ratio' => 'integer',
        'is_base'    => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}

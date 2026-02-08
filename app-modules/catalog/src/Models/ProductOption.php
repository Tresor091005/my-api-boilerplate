<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $handle
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, ProductOptionValue> $values
 * @property-read int|null $values_count
 *
 * @method static Builder<static>|ProductOption newModelQuery()
 * @method static Builder<static>|ProductOption newQuery()
 * @method static Builder<static>|ProductOption query()
 * @method static Builder<static>|ProductOption whereCreatedAt($value)
 * @method static Builder<static>|ProductOption whereHandle($value)
 * @method static Builder<static>|ProductOption whereId($value)
 * @method static Builder<static>|ProductOption whereName($value)
 * @method static Builder<static>|ProductOption whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ProductOption extends Model
{
    use SharedTraits;

    protected $table = 'catalog_product_options';

    protected $fillable = [
        'handle',
        'name',
    ];

    protected $casts = [
        'id'         => 'string',
        'handle'     => 'string',
        'name'       => 'string',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class, 'option_id', 'id');
    }
}

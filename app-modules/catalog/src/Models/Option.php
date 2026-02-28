<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read VariantOptionValue|null $pivot
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 * @property-read Collection<int, OptionValue> $values
 * @property-read int|null $values_count
 *
 * @method static Builder<static>|Option newModelQuery()
 * @method static Builder<static>|Option newQuery()
 * @method static Builder<static>|Option query()
 * @method static Builder<static>|Option whereCode($value)
 * @method static Builder<static>|Option whereCreatedAt($value)
 * @method static Builder<static>|Option whereId($value)
 * @method static Builder<static>|Option whereName($value)
 * @method static Builder<static>|Option whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Option extends Model
{
    use SharedTraits;

    protected $table = 'catalog_options';

    protected $fillable = [
        'code',
        'name',
    ];

    protected $casts = [
        'id'         => 'string',
        'code'       => 'string',
        'name'       => 'string',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(OptionValue::class, 'option_id', 'id');
    }

    public function products(): BelongsToMany
    {
        // TODO verify it's the right way to get the products
        return $this->belongsToMany(
            Product::class,
            'catalog_variant_option_value',
            'option_id',
            'product_id'
        )->distinct()->using(VariantOptionValue::class);
    }
}

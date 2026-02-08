<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $option_id
 * @property string $handle
 * @property string $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductOption $option
 * @property-read ProductVariantOptionValue|null $pivot
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read int|null $variants_count
 *
 * @method static Builder<static>|ProductOptionValue newModelQuery()
 * @method static Builder<static>|ProductOptionValue newQuery()
 * @method static Builder<static>|ProductOptionValue query()
 * @method static Builder<static>|ProductOptionValue whereCreatedAt($value)
 * @method static Builder<static>|ProductOptionValue whereHandle($value)
 * @method static Builder<static>|ProductOptionValue whereId($value)
 * @method static Builder<static>|ProductOptionValue whereOptionId($value)
 * @method static Builder<static>|ProductOptionValue whereUpdatedAt($value)
 * @method static Builder<static>|ProductOptionValue whereValue($value)
 *
 * @mixin \Eloquent
 */
class ProductOptionValue extends Model
{
    use SharedTraits;

    protected $table = 'catalog_product_option_values';

    protected $fillable = [
        'option_id',
        'handle',
        'value',
    ];

    protected $casts = [
        'id'         => 'string',
        'option_id'  => 'string',
        'handle'     => 'string',
        'value'      => 'string',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'option_id', 'id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'catalog_product_variant_option_value',
            'option_value_id',
            'variant_id'
        )->using(ProductVariantOptionValue::class)
            ->withTimestamps();
    }
}

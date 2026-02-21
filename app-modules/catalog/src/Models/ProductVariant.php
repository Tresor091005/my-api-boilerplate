<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $product_id
 * @property string $sku
 * @property string|null $unit_code
 * @property int $min_quantity
 * @property int|null $max_quantity
 * @property int $step
 * @property bool $is_default
 * @property bool $is_stockable
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ProductVariantOptionValue|null $pivot
 * @property-read Collection<int, ProductOptionValue> $optionValues
 * @property-read int|null $option_values_count
 * @property-read Product $product
 * @property-read Unit|null $unit
 * @property-read Collection<int, Price> $prices
 * @property-read int|null $prices_count
 *
 * @method static Builder<static>|ProductVariant newModelQuery()
 * @method static Builder<static>|ProductVariant newQuery()
 * @method static Builder<static>|ProductVariant query()
 * @method static Builder<static>|ProductVariant whereCreatedAt($value)
 * @method static Builder<static>|ProductVariant whereId($value)
 * @method static Builder<static>|ProductVariant whereIsActive($value)
 * @method static Builder<static>|ProductVariant whereIsDefault($value)
 * @method static Builder<static>|ProductVariant whereIsStockable($value)
 * @method static Builder<static>|ProductVariant whereMaxQuantity($value)
 * @method static Builder<static>|ProductVariant whereMinQuantity($value)
 * @method static Builder<static>|ProductVariant whereProductId($value)
 * @method static Builder<static>|ProductVariant whereSku($value)
 * @method static Builder<static>|ProductVariant whereStep($value)
 * @method static Builder<static>|ProductVariant whereUnitCode($value)
 * @method static Builder<static>|ProductVariant whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ProductVariant extends Model
{
    use SharedTraits;

    protected $table = 'catalog_product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'unit_code',
        'min_quantity',
        'max_quantity',
        'step',
        'is_default',
        'is_stockable',
        'allow_negative_stock',
        'is_active',
    ];

    protected $casts = [
        'id'                   => 'string',
        'product_id'           => 'string',
        'sku'                  => 'string',
        'unit_code'            => 'string',
        'min_quantity'         => 'integer',
        'max_quantity'         => 'integer',
        'step'                 => 'integer',
        'is_default'           => 'boolean',
        'is_stockable'         => 'boolean',
        'allow_negative_stock' => 'boolean',
        'is_active'            => 'boolean',
        'created_at'           => 'immutable_datetime',
        'updated_at'           => 'immutable_datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_code', 'code');
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'catalog_product_variant_option_value',
            'variant_id',
            'option_value_id'
        )->using(ProductVariantOptionValue::class);
    }

    public function prices(): MorphMany
    {
        return $this->morphMany(Price::class, 'priceable');
    }
}

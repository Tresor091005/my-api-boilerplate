<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $product_id
 * @property string $variant_id
 * @property string $option_value_id
 * @property string $option_id
 * @property-read ProductOption $option
 * @property-read ProductOptionValue $optionValue
 * @property-read Product $product
 * @property-read ProductVariant $variant
 *
 * @method static Builder<static>|ProductVariantOptionValue newModelQuery()
 * @method static Builder<static>|ProductVariantOptionValue newQuery()
 * @method static Builder<static>|ProductVariantOptionValue query()
 * @method static Builder<static>|ProductVariantOptionValue whereId($value)
 * @method static Builder<static>|ProductVariantOptionValue whereOptionId($value)
 * @method static Builder<static>|ProductVariantOptionValue whereOptionValueId($value)
 * @method static Builder<static>|ProductVariantOptionValue whereProductId($value)
 * @method static Builder<static>|ProductVariantOptionValue whereVariantId($value)
 *
 * @mixin \Eloquent
 */
class ProductVariantOptionValue extends Pivot
{
    use SharedTraits;

    protected $table = 'catalog_product_variant_option_value';

    protected $casts = [
        'id'              => 'string',
        'variant_id'      => 'string',
        'option_value_id' => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'id');
    }

    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(ProductOptionValue::class, 'option_value_id', 'id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'option_id', 'id');
    }
}
